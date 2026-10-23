# 菜谱数据导入（本地 / 远程服务器）

## 一、数据在哪

| 东西 | 位置 | 说明 |
|---|---|---|
| 菜谱数据 | `import/recipes_full.json` | **唯一数据源**，当前 510 条 |
| 图片 | `public/images/*.jpg` | 文件名与 JSON 里 `image` 字段对应 |
| 图片（线上） | 腾讯云 COS | **生产走 COS，不是本地目录**（见下） |
| 目标表 | `recipes` / `recipe_ingredients` / `recipe_directions` | 三张表 |

### 图片地址是怎么决定的

`Recipe::image_src` 的逻辑：

```
public/images/{image} 存在  →  返回 /images/{image}        （本地文件）
不存在                      →  走 CosService 返回 CDN 地址  （COS 对象）
```

生产服务器上 `public/images` 只有一部分图，所以新数据会返回
`https://recipe-1251274653.file.myqcloud.com/{image}`。
**如果这张图没传到 COS，接口就返回了这个地址但实际 404** —— 这就是「图片全都找不到」的原因。

所以线上图片必须传到 COS：

```bash
php artisan cos:sync-images --dry-run    # 先看要传多少
php artisan cos:sync-images              # 只传 COS 上缺的
php artisan cos:sync-images --force      # 全部覆盖
```

> 传到服务器 `public/images` 也能让 `image_src` 走本地路径生效，
> 但和现有 COS 架构不一致（CDN 加速失效），不推荐。

Seerder 有两个，**默认用增量那个**：

| Seeder | 行为 | 什么时候用 |
|---|---|---|
| `RecipesIncrementalSeeder` | **保留现有数据**，只插入没有的菜谱 | 日常加数据（推荐） |
| `RecipesTableSeeder` | `TRUNCATE` 三张表后全量重建 | 首次部署 / 要彻底重来 |

### 增量导入的去重规则

按顺序判断，命中任一即视为重复、跳过：

1. **菜名** —— `trim` + 连续空白归一 + 转小写后比较
2. **图片文件名** —— `image` 字段相同时视为同一道菜

> 注意：菜名相同但内容不同的（比如线上有「红烧肉」是别人写的版本，本地也有一个「红烧肉」），
> 会被判定为重复并跳过，不会覆盖线上。需要覆盖时改用全量重建。

---

## 二、本地导入

```bash
cd C:/code/tianlong_recipe_backend

# 1. 增量导入（保留现有，只补新的）—— 推荐
php artisan db:seed --class="Database\Seeders\RecipesIncrementalSeeder"

# 2. 清缓存，让接口立刻返回新数据
php artisan cache:clear

# 3. 验证
php artisan tinker --execute="echo DB::table('recipes')->count();"
```

输出长这样：

```
待处理 510 条，数据库现有 420 条

========== 增量导入结果 ==========
新增:        90 条
跳过(重名):  420 条
跳过(同图):  0 条
无效数据:    0 条
数据库:      420 -> 510 条
=================================
```

可以反复执行，第二次跑就是全跳过（幂等）。

如果只想补 meta 字段（不动菜谱本身）：

```bash
php artisan db:seed FillRecipeMetaFieldsSeeder
```

---

## 三、远程服务器导入

### 情况 A：第一次部署（线上还是空的 / 代码也要更新）

```bash
# 1. 打包（不含 vendor / node_modules / .env，含 public/images 和 recipes_full.json）
bash deploy/pack.sh release

# 2. 上传（读取 deploy/ftp.env 里的服务器配置）
bash deploy/upload.sh release/tianlong-recipe-YYYYmmdd-HHMMSS.tar.gz

# 3. 登录服务器
ssh root@<服务器IP>
cd <站点目录>            # 例如 /www/wwwroot/recipe.80shihua.com
tar -xzf tianlong-recipe-YYYYmmdd-HHMMSS.tar.gz

# 4. 跑部署脚本（会问你要不要 migrate、要不要导入菜谱数据）
bash deploy/deploy.sh
```

`deploy.sh` 执行到第 7.2 步时会停下来问你：

```
[!] import/recipes_full.json 含 510 条菜谱
[!] 导入会【清空】线上 recipes / recipe_ingredients / recipe_directions 三张表后重建
确认导入菜谱数据? 输入 YES 大写继续:
```

- 输 `YES` → 导入
- 直接回车 → 跳过，线上数据不动

### 情况 B：站点已上线，只想加新菜谱数据（推荐）

**图片直接进 COS，不用经过服务器。**

```bash
# 1. 把新图片同步到 COS（本地执行，已存在的自动跳过）
php artisan cos:sync-images --dry-run     # 先看要传多少
php artisan cos:sync-images

# 2. 只传数据文件到服务器（走 FTP，不需要根目录写权限）
bash deploy/ftp_put_files.sh

# 3. 服务器上增量导入（保留线上数据）
cd <站点目录>
php artisan db:seed --class="Database\Seeders\RecipesIncrementalSeeder" --force
php artisan cache:clear
php artisan config:clear
```

> 想打包传输也行：`bash deploy/sync_data.sh diff` 生成 53MB 包，
> 但**上传到根目录会 553 失败**（该服务器根目录不可写），必须走 `ftp_put_files.sh`。
> 而且包里带的 public/images 对 COS 架构其实没用——真正生效的是 `cos:sync-images`。

### 情况 C：手动操作（不依赖脚本）

1. 用 FTP 工具（FileZilla / WinSCP）把 `import/recipes_full.json` 和 `public/images/` 传到站点目录
2. SSH 登录服务器执行：

```bash
cd /www/wwwroot/<站点>
php artisan db:seed --class="Database\Seeders\RecipesIncrementalSeeder" --force
php artisan cache:clear
```

---

## 四、服务器前置检查

导入前确认这几项，否则容易失败：

```bash
php -v                                   # >= 8.2
php -m | grep -E 'fileinfo|mbstring|pdo_mysql'   # 三个都要有
ls -la import/recipes_full.json          # 文件在
ls public/images | wc -l                 # 图片数量
df -h .                                  # 磁盘够（图片约 108MB）
```

权限（宝塔默认站点属主是 www）：

```bash
chown -R www:www /www/wwwroot/<站点>
chmod -R 775 storage bootstrap/cache public/images
```

---

## 五、常见问题

### Q1：导入后接口还是旧数据

RecipeController 有 600 秒缓存。执行：

```bash
php artisan cache:clear
php artisan config:clear
```

### Q2：菜谱有数据但图片 404

JSON 里 `image` 有值，但服务器 `public/images/` 缺文件。检查：

```bash
php -r '
  $j = json_decode(file_get_contents("import/recipes_full.json"), true);
  $miss = 0;
  foreach ($j as $r) { $i = $r["image"] ?? ""; if ($i && !is_file("public/images/$i")) $miss++; }
  echo "缺失图片: $miss\n";
'
```

补传缺失图片，或重新上传完整 `public/images`。

### Q3：`SQLSTATE[23000]: Cannot add or update a child row`

外键冲突。Seeder 里已用 `SET FOREIGN_KEY_CHECKS=0`，若仍报错，确认是 MySQL 而非 SQLite：

```bash
grep DB_CONNECTION .env     # 应为 mysql
```

### Q4：导入很慢 / 超时

510 条约 10 秒。上万条时建议服务器上直接跑，不要在 SSH 里用 `nohup` 断开：

```bash
nohup php artisan db:seed --class="Database\Seeders\RecipesTableSeeder" --force > seed.log 2>&1 &
tail -f seed.log
```

### Q5：想保留线上已有菜谱，只追加新菜谱

直接用增量 Seeder 即可，它不会动已有数据：

```bash
php artisan db:seed --class="Database\Seeders\RecipesIncrementalSeeder" --force
```

如果发现某道菜线上有、但内容（配料/步骤）是错的，想吃本地版本覆盖：

```bash
# 先删掉线上那条，再跑增量导入
php artisan tinker --execute='
  $r = DB::table("recipes")->where("name","红烧肉")->first();
  if ($r) {
    DB::table("recipe_ingredients")->where("recipe_id",$r->id)->delete();
    DB::table("recipe_directions")->where("recipe_id",$r->id)->delete();
    DB::table("recipes")->where("id",$r->id)->delete();
    echo "已删除\n";
  }
'
php artisan db:seed --class="Database\Seeders\RecipesIncrementalSeeder" --force
```

### Q6：增量导入后条数比 JSON 里的少

说明有重名/同图被跳过了，正常。看 Seeder 输出的「跳过(重名)」计数即可。
如果想确认具体哪些被跳过，可先跑一次查重：

```bash
php artisan tinker --execute='
  $j = json_decode(file_get_contents("import/recipes_full.json"), true);
  $names = DB::table("recipes")->pluck("name")->map(fn($n)=>mb_strtolower(trim($n)))->all();
  $dup = array_filter($j, fn($r)=>in_array(mb_strtolower(trim($r["name"]??"")), $names));
  echo "会被跳过: ".count($dup)." 条\n";
'
```

---

## 六、脚本对照

| 脚本 | 用途 |
|---|---|
| `deploy/pack.sh` | 全量发布打包（代码 + 图片 + 数据） |
| `deploy/sync_data.sh` | 只打包数据（图片 + JSON），增量更新用 |
| `deploy/upload.sh` | FTP 上传包，读 `deploy/ftp.env` |
| `deploy/deploy.sh` | 服务器端部署：依赖、权限、迁移、导入数据、优化 |
| `deploy/check-env.sh` | 环境自检 |
| `deploy/fix-vendor.sh` | vendor 目录权限问题修复 |
