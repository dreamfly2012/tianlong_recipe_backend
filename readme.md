# 天龙食谱 API 后端（Laravel 12 + Vue 3 SPA）

> 本仓库是「天龙食谱」的后端 + 管理后台 SPA。本文档同时包含**项目说明**与**完整接口文档**，
> 可直接喂给前端小程序 / 移动端对接使用。所有接口路径均以 `/api` 为前缀。

---

## 一、技术栈

- **后端**：Laravel 12（PHP ^8.2）、MySQL 8、Redis（缓存，可选）、Sanctum Token 鉴权
- **前端**：Vue 3 + Vite 6 + Tailwind 4（管理后台 SPA，源码位于 `resources/assets/js`）
- **存储**：本地 `public/images` 磁盘；配置了腾讯云 COS 时同步上传
- **测试**：PHPUnit（SQLite `:memory:` + array 缓存，无需 MySQL/Redis）

---

## 二、功能概览

- 食谱 CRUD（含配料 `ingredients`、制作步骤 `directions`、分类 `category`）
- 图片上传（本地落盘 + COS 同步）
- 用户注册 / 登录 / 登出 / **微信小程序登录**（code 换 token，自动注册）
- 收藏 / 取消收藏 / 收藏列表 / 是否收藏
- 列表、详情、按分类筛选、搜索、每日推荐、AI 推荐、个性化推荐、随机生成食谱

---

## 三、环境要求与安装

### 环境要求

- PHP >= 8.2（需开启 `openssl`、`mbstring`、`pdo_sqlite` 等扩展）
- Composer 2
- Node.js 18+（构建前端）
- MySQL 8（或修改 `.env` 使用 SQLite 亦可本地开发）

### 安装步骤

```bash
# 1. 安装 PHP 依赖
composer install

# 2. 生成应用密钥
php artisan key:generate

# 3. 配置环境变量（复制后按需修改）
cp .env.example .env

# 4. 数据库迁移（如需示例数据可加 --seed）
php artisan migrate --seed

# 5. 安装并构建前端
npm install
npm run build

# 6. 启动后端（默认 127.0.0.1:8000）
php artisan serve
```

> 微信登录、COS 上传在对应环境变量未配置时自动降级为「本地模式 / 模拟登录」，不影响本地联调。

### 关键环境变量（`.env`）

| 变量 | 说明 |
| --- | --- |
| `APP_URL` | 应用访问地址（用于拼接本地图片 URL） |
| `DB_CONNECTION` / `DB_*` | 数据库连接 |
| `COS_APP_ID` | 腾讯云 APPID |
| `COS_SECRET_ID` / `COS_SECRET_KEY` | COS 密钥 |
| `COS_REGION` | 地域，默认 `ap-guangzhou` |
| `COS_BUCKET` | 存储桶（**不带**数字 app_id 后缀） |
| `COS_CDN` | 可选，自定义 CDN 域名；配置后图片走 CDN |
| `COS_PATH_PREFIX` | 可选，对象存储路径前缀 |
| `WECHAT_MINIPROGRAM_APPID` / `WECHAT_MINIPROGRAM_SECRET` | 微信小程序登录凭据（留空则进入模拟登录） |

---

## 四、COS 存储说明（重要，已修正）

> ⚠️ 旧文档里「修改 `vendor/overtrue/laravel-filesystem-cos` 源码」的方案**已废弃**。
> 该第三方胶水包在 Laravel 12 / Flysystem 3 下注册的 Storage 实例不是 Laravel 的
> `FilesystemAdapter`，会导致 `Storage::disk('cos')->put()` 报「undefined method」。

当前实现（自封装，不再依赖已移除的胶水包）：

- 依赖底层包 `overtrue/flysystem-cos`（已在 `composer.json` 中）。
- 通过 `app/Services/CosService.php` 用其 `CosAdapter` 自建 `Illuminate\Filesystem\FilesystemAdapter`，
  逻辑完全可控，且**不受 `composer update` 冲刷 vendor 补丁的影响**。
- 上传时：`RecipeController` 始终先写入本地 `images` 磁盘，配置了完整 COS 凭据时再同步上传。
- 图片访问 URL：模型 `Recipe::image_src` 访问器优先返回本地 `public/images` 下的文件，
  否则交给 `CosService::url()`（配置了 CDN 返回 CDN URL，否则回退 `/images/...`）。
- **升级依赖时无需再手动打 vendor 补丁**，只要保证 `overtrue/flysystem-cos` 与 `CosService` 存在即可。

---

## 五、接口约定（所有前端通用）

### Base URL

```
http://<你的域名或IP>/api
```
本地开发示例：`http://127.0.0.1:8000/api`

### 统一响应格式

所有接口返回如下 JSON 信封（`code = 0` 表示成功）：

```json
{
  "code": 0,
  "message": "操作成功",
  "data": { }
}
```

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `code` | int | 业务码：`0` 成功；`1` 通用错误；`2` 参数校验错误；`401` 未授权；`403` 禁止；`404` 不存在；`500` 服务器错误 |
| `message` | string | 提示文案（中文） |
| `data` | any | 业务数据；分页接口 `data` 内再嵌套 `data` 数组（见下） |

### 分页结构

列表类接口 `data` 为分页对象，列表项位于 `data.data`：

```json
{
  "code": 0,
  "message": "获取食谱列表成功",
  "data": {
    "data": [ /* 食谱数组 */ ],
    "current_page": 1,
    "per_page": 15,
    "total": 30,
    "last_page": 2,
    "from": 1,
    "to": 15
  }
}
```

> 前端取列表：`response.data.data.data`（外层 `.data` 是信封，内层 `.data` 是分页对象）。

### 鉴权方式

- 使用 **Laravel Sanctum Token**（Bearer）。
- 登录 / 注册 / 微信登录成功后，返回 `data.api_token`（同时给 `data.token` 别名）。
- 需鉴权的接口在请求头携带：

```
Authorization: Bearer <api_token>
```

- 未携带或失效时返回 `{"code":401,"message":"Unauthenticated."}`，前端应清除本地用户并跳转登录。

---

## 六、接口文档

### 6.1 认证 Auth

#### POST `/api/login` 登录

请求体（JSON）：

```json
{ "email": "user@example.com", "password": "password123" }
```

响应 `200`：

```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "authenticated": true,
    "api_token": "1|abc...",
    "token": "1|abc...",
    "user": { "id": 1, "name": "用户名", "email": "user@example.com" }
  }
}
```

#### POST `/api/register` 注册

请求体（JSON）：

```json
{
  "name": "张三",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

响应 `201`：

```json
{
  "code": 0,
  "message": "注册成功",
  "data": { "registered": true, "api_token": "1|abc...", "token": "1|abc...", "user": { } }
}
```

#### POST `/api/logout` 登出（需鉴权）

响应 `200`：`{ "code": 0, "message": "已退出登录", "data": { "done": true } }`

#### POST `/api/wechat/login` 微信小程序登录

请求体（JSON）：

```json
{ "code": "081VS80w3JWVyK2a2v0w3CxW80wVS80m", "user_info": { "nickName": "张三", "avatarUrl": "https://..." } }
```

- `code` 必填；`user_info` 选填（用于首次自动注册时写入昵称/头像）。
- 微信凭据未配置时进入**模拟登录**（本地开发可用）。
- 用户不存在则自动注册（email 用 `wechat_<openid>@example.com`）。

响应 `200`：`{ "code": 0, "message": "登录成功", "data": { "authenticated": true, "api_token": "...", "token": "...", "user": { } } }`

---

### 6.2 食谱 Recipes（REST 资源路由，推荐小程序使用）

| 方法 | 路径 | 鉴权 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/recipes` | 否 | 食谱列表（分页，按创建时间倒序） |
| GET | `/api/recipes/{id}` | 否 | 食谱详情（含 `user`/`category`/`ingredients`/`directions`） |
| POST | `/api/recipes` | 是 | 创建食谱（multipart/form-data，图片为文件） |
| GET | `/api/recipes/{id}/edit` | 是 | 获取编辑所需初始数据（表单 + 分类） |
| PUT / PATCH | `/api/recipes/{id}` | 是 | 更新食谱 |
| DELETE | `/api/recipes/{id}` | 是 | 删除食谱 |

#### GET `/api/recipes` 列表

查询参数：`page`（页码，默认 1）。响应见「分页结构」，列表项字段见 6.4。

#### GET `/api/recipes/{id}` 详情

响应 `200`：

```json
{
  "code": 0,
  "message": "获取食谱详情成功",
  "data": {
    "id": 2,
    "name": "宫保鸡丁",
    "description": "经典川菜",
    "image": "AbC123.jpg",
    "image_src": "https://cdn.example.com/AbC123.jpg",
    "category_id": 3,
    "user_id": 1,
    "created_at": "2026-10-20T10:00:00.000000Z",
    "updated_at": "2026-10-20T10:00:00.000000Z",
    "user": { "id": 1, "name": "admin" },
    "category": { "id": 3, "name": "川菜", "description": null, "parent_id": 0 },
    "ingredients": [
      { "id": 5, "recipe_id": 2, "name": "鸡胸肉", "qty": "300g" }
    ],
    "directions": [
      { "id": 7, "recipe_id": 2, "description": "鸡肉切丁腌制" }
    ]
  }
}
```

#### POST `/api/recipes` 创建（需鉴权，multipart/form-data）

字段：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `name` | string | 是 | 名称，≤255 |
| `description` | string | 是 | 描述，≤3000 |
| `image` | file | 是 | 图片，jpeg/png/jpg/gif/webp，≤2048KB |
| `category_id` | int | 是 | 分类 ID（需存在） |
| `ingredients` | array | 是 | 配料数组，至少 1 项 |
| `ingredients[].name` | string | 是 | 配料名，≤255 |
| `ingredients[].qty` | string | 是 | 用量，≤255 |
| `directions` | array | 是 | 步骤数组，至少 1 项 |
| `directions[].description` | string | 是 | 步骤说明，≤3000 |

响应 `201`：`{ "code": 0, "message": "食谱创建成功", "data": { "id": 12 } }`

#### PUT / PATCH `/api/recipes/{id}` 更新（需鉴权）

字段同创建，区别：
- `image` 改为**选填**（`sometimes`，有文件才替换）。
- `ingredients[].id` / `directions[].id` **可选**：传已有记录 id 表示更新，不传表示新增；未提交的旧记录会被删除。

响应 `200`：`{ "code": 0, "message": "食谱更新成功", "data": { "id": 12 } }`

#### DELETE `/api/recipes/{id}` 删除（需鉴权）

响应 `200`：`{ "code": 0, "message": "食谱删除成功", "data": null }`

---

### 6.3 便捷 / 兼容接口（dot 风格，旧 SPA 使用）

> 以下为兼容历史前端保留的「点号」路由，小程序对接**建议优先用 6.2 的 REST 接口**；
> 收藏类接口为 GET 动词（历史约定），需鉴权。

| 方法 | 路径 | 鉴权 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/recipe.lists?id=&page=` | 否 | 列表，可选按 `category_id` 筛选 |
| GET | `/api/recipe.info?id=` | 否 | 详情（含 `directions`/`ingredients`/`category`） |
| GET | `/api/recipe.category?parent_id=0` | 否 | 分类列表，`parent_id` 指定父级 |
| GET | `/api/recipe.search?name=` | 否 | 按名称模糊搜索 |
| GET | `/api/recipe.daily` | 否 | 每日推荐（分页 15） |
| GET | `/api/recipe.ai` | 否 | AI 推荐（分页 15） |
| GET | `/api/recipe.personalized` | 否 | 个性化推荐（分页 2） |
| POST | `/api/recipe.generate` | 否 | 随机返回一个完整菜谱 |
| GET | `/api/recipe.favourites` | 是 | 我的收藏列表（分页 15） |
| GET | `/api/recipe.favourite?recipe_id=` | 是 | 收藏指定食谱 |
| GET | `/api/recipe.unfavourite?recipe_id=` | 是 | 取消收藏 |
| GET | `/api/recipe.isfavourite?recipe_id=` | 是 | 是否已收藏 → `data.isFavorite` |

---

### 6.4 食谱数据字段（Recipe）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int | 主键 |
| `name` | string | 名称 |
| `description` | string | 描述 |
| `image` | string | 图片文件名（如 `AbC123.jpg`） |
| `image_src` | string\|null | **完整可访问的图片 URL**，前端直接用此字段渲染 |
| `category_id` | int | 分类 ID |
| `user_id` | int | 创建者 ID |
| `created_at` / `updated_at` | string | 时间戳（ISO8601） |
| `user` | object | 创建者 `{id, name}`（详情接口返回） |
| `category` | object | 分类 `{id, name, description, parent_id}`（详情/列表接口返回） |
| `ingredients` | array | 配料 `[{id, recipe_id, name, qty}]` |
| `directions` | array | 步骤 `[{id, recipe_id, description}]` |

### 6.5 分类数据字段（RecipeCategory）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int | 主键 |
| `name` | string | 分类名 |
| `description` | string\|null | 描述 |
| `parent_id` | int | 父分类 ID，`0` 为顶级 |

---

## 七、前端小程序对接要点（Checklist）

1. **基地址**：所有请求拼 `/api` 前缀；建议从配置读取，区分开发/生产。
2. **Token 存储**：登录/注册/微信登录拿到 `data.api_token`，存到本地（如 `wx.setStorageSync`），
   每次请求在 header 带 `Authorization: Bearer <api_token>`。
3. **401 处理**：收到 `code=401` 或 HTTP 401 → 清本地 token，跳登录页（微信登录可静默重新获取 code）。
4. **图片**：直接用 `image_src` 字段渲染，**不要**自行拼接路径；未配 COS 时为 `/images/xxx.jpg` 相对路径，
   需结合基地址拼成完整 URL（或配置 `COS_CDN` 后即为绝对 URL）。
5. **分页**：列表数据在 `response.data.data.data`（信封 `data` → 分页对象 `data` → 列表数组）。
6. **创建/更新食谱**：用 `multipart/form-data` 上传图片文件；配料、步骤传数组。
7. **收藏接口**为 GET + `recipe_id` 查询参数（历史约定，勿改动词）。

---

## 八、测试

```bash
composer test
```

测试在 SQLite `:memory:` + array 缓存下运行，无需 MySQL/Redis。
