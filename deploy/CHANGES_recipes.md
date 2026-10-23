# 真实菜谱数据批量采集

> 抓取**下厨房真实用户菜谱**：菜名、描述、配料、步骤、原图均为站上真实内容，
> 不使用模板生成或伪造数据。

## 结论先行：怎么绕开验证码

实测对比（同一台机器、同一 IP、同一时段）：

| 请求方式 | 验证码情况 |
|---|---|
| curl + cookie 直接请求详情页 | 约每 3 条触发 1 次 |
| Python urllib | 连列表页都被拦 |
| App API（api.xiachufang.com） | 需 App 签名，返回"请升级 app" |
| **真实 Chrome（CDP 驱动）** | **连续 18 条 0 次验证码** |
| **真实 Chrome 无头模式** | **连续 6 条 0 次验证码** |

**验证码主要是浏览器指纹判定，不是单纯 IP 判定。** 所以：
- 换免费代理 IP **没用**，反而更糟（机房 IP 风控评分更差）
- 正确做法是**用真实浏览器抓**

## 为什么不建议用免费代理池

1. 网上公开的免费代理可用率普遍低于 5%，且经常超时
2. 下厨房对机房 IP 段（阿里云/腾讯云等）风控评分更差，触发验证码更频繁
3. 免费代理不可信，抓取流量可能被劫持或注入
4. 代理越多、越换，行为特征越像机器人，反而更容易被拦

> 如果确实需要更高并发（比如多机同时跑），唯一可靠的途径是**付费住宅代理**
> （青果、快代理、芝麻、BrightData 等）。
> 本脚本已预留 `--proxy` 参数，可直接接入。

## 架构

采集与加工分离，改解析逻辑不用重新访问网站：

```
import/xcf_ids.txt          349 万条真实菜谱 ID（来自官方 sitemap）
        ↓
import/xcf_collect.mjs      真实 Chrome 采集原始 JSON-LD
        ↓
import/xcf_raw.jsonl        原始数据（每行 {id, url, jsonld}）
        ↓
import/xcf_process.py       解析配料/步骤、分类、下载原图
        ↓
import/xcf_recipes.jsonl    规范化数据
        ↓
import/merge_recipes.py     合并原始 84 条
        ↓
import/recipes_full.json    → Laravel Seeder 入库
```

## 使用步骤

### 1. 全自动采集（推荐）

```bash
# 保守节奏（推荐，默认就是这样）
node import/xcf_collect.mjs --limit 20000 --headless

# 更保守，适合被限流后恢复期
node import/xcf_collect.mjs --limit 20000 --headless --delay-min 15 --delay-max 30

# 带窗口跑（万一弹验证码，你能直接滑）
node import/xcf_collect.mjs --limit 20000
```

默认节奏：
- 每条之间随机 **8~16 秒**
- 每采 **50 条**长休息 **60~180 秒**
- 触发 **HTTP 429「你访问得太多了」** 自动暂停 15/30/45 分钟后重试同一条，连续 3 次才停

其他参数：
| 参数 | 默认 | 说明 |
|---|---|---|
| `--delay-min` / `--delay-max` | 8 / 16 | 每条间隔秒数 |
| `--rest-every` | 50 | 每多少条长休息（0=不休） |
| `--rest-min` / `--rest-max` | 60 / 180 | 长休息秒数 |
| `--max-captcha` | 15 | 最多处理几次验证码 |
| `--proxy` | 无 | 付费住宅代理地址 |

特性：
- 断点续传（按 `xcf_raw.jsonl` 里的 id 去重）
- 有头模式遇到验证码会**停下来等你滑动**，滑完自动继续
- 无头模式遇到验证码会指数退避（60s→120s→…），连续 5 次才停
- 建议先跑小批量试水：`--limit 50`

### 2. 加工数据

```bash
python import/xcf_process.py
```

### 3. 合并入库

```bash
python import/merge_recipes.py

docker compose exec php php artisan db:seed --class=Database\\Seeders\\RecipesTableSeeder
docker compose exec php php artisan db:seed --class=Database\\Seeders\\FillRecipeMetaFieldsSeeder
```

## 速度参考与限流

下厨房有两层限制：

1. **滑动验证码** —— 浏览器指纹判定，真实 Chrome 基本不触发
2. **HTTP 429「你访问得太多了」** —— 按 IP 累计请求频率判定，**这才是主要瓶颈**

实测：连续 24 分钟跑 555 次请求（约 425 条成功）后触发 429，随后整站对 curl 返回 404（临时封禁）。
所以节奏必须放慢。

按默认节奏（8~16s + 每 50 条休息）估算：

| 目标 | 预计耗时 |
|---|---|
| 500 | ~1.5 小时 |
| 2,000 | ~6 小时 |
| 5,000 | ~15 小时 |
| 20,000 | ~60 小时 |

被限流后的处理：
- 已经触发 429 → 停手，隔 **1~2 小时**再跑（脚本会自动续传）
- 解封表现：`curl https://www.xiachufang.com/` 恢复 200（不是 404）
- 恢复期首轮用 `--delay-min 15 --delay-max 30` 慢慢跑

> 图片同理：图床 `chuimg.com` 限流时也是 404，恢复后用
> `python import/xcf_refill_images.py` 一键补齐缺失图片。

## 备选：curl 方案（较快但易触发验证码）

```bash
node import/xcf_cookie.mjs                    # 先过验证拿 cookie
python import/xcf_crawl.py --limit 200        # curl 批量抓
```

约 3 条触发 1 次验证码，会弹浏览器让你滑。适合少量补充。

## 字段映射

| recipes 字段 | 来源 | 说明 |
|---|---|---|
| `name` | JSON-LD `name` | 原始菜名 |
| `description` | JSON-LD `description` | 作者写的简介 |
| `image` | 原图 URL（去 CDN 缩略参数） | 下载到 `public/images/` |
| `category_id` | 菜名/关键词 → 八大菜系 | 未命中按常见分布兜底 |
| `cooking_time` | 按步骤/配料数估算 | 估算值 |
| `difficulty` | 按步骤/配料数估算 | 估算值 |
| `tags` | JSON-LD `keywords` | 原始标签 |
| `ingredients` | `recipeIngredient` | 拆分 name/qty，支持「一只尖椒」这类中文数量 |
| `directions` | `recipeInstructions` | 按 ①②③ / 1. 2. / 换行 / 句号切分 |

> `cooking_time` / `difficulty` 是估算值（原页面不提供），属合理推断。

## 图片

- 原图无水印（已验证：原图 100~330KB，与站点一致）
- 存 `public/images/{32位hash}.jpg`
- `Recipe::image_src` 优先返回本地 `/images/{image}`
