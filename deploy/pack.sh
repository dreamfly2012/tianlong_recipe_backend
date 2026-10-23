#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 生产部署打包脚本
#  用法: bash deploy/pack.sh [输出目录]
#  产物: tianlong-recipe-YYYYmmdd-HHMMSS.tar.gz
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# 输出目录一律先切到项目根再解析，相对路径参数（如 release）始终以仓库根为基准；
# 反斜杠写法 .\release\ 会被解析成奇怪的目录，导致产物落在别处甚至根目录。
OUT_DIR="${1:-release}"
mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

if [ "$OUT_DIR" = "$ROOT" ]; then
  echo "!! 输出目录不能是项目根目录（tar 会把产物打进包里），请用: bash deploy/pack.sh release" >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
PKG="tianlong-recipe-${STAMP}.tar.gz"

echo "    输出目录: $OUT_DIR"

# ---------- 1. 清理本地垃圾（仅缓存/日志，不动业务数据） ----------
echo "==> 清理本地缓存与日志"
find storage/framework/views -type f ! -name '.gitignore' -delete 2>/dev/null || true
find storage/framework/cache -type f ! -name '.gitignore' -delete 2>/dev/null || true
find storage/framework/sessions -type f ! -name '.gitignore' -delete 2>/dev/null || true
rm -f storage/logs/*.log
rm -f .phpunit.result.cache
rm -rf public/hot
# 二次清理：确保视图缓存为空（删除后可能有进程再次写入）
find storage/framework/views -type f ! -name '.gitignore' -delete 2>/dev/null || true

# ---------- 1.1 保证 Laravel 必需目录骨架存在 ----------
# 这些目录在 git 中是空目录（无 .gitignore 占位），打包时若不存在会导致
# 服务器上 Laravel 报 "Please provide a valid cache path" 之类的错误。
echo "==> 补齐 Laravel 必需目录"
for d in storage/app storage/app/private storage/app/public \
         storage/framework storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache; do
  mkdir -p "$d"
  [ -f "$d/.gitignore" ] || printf '*\n!.gitignore\n' > "$d/.gitignore"
done

# ---------- 1.2 摘掉 public/storage 符号链接 ----------
# 它是 php artisan storage:link 建的链接，本机常指向 Docker 路径 /var/www/...，
# 在 Windows 宿主机上是死链。bsdtar（Windows 自带 tar.exe）遍历时会对死链 stat
# 失败并报 "Cannot stat: Invalid argument" 中断打包，故先 unlink：rm 不带结尾斜杠
# 只删链接本身，不会跟进删除链接目标。服务器上由 deploy.sh 的 storage:link 重建。
if [ -L public/storage ]; then
  echo "==> 移除 public/storage 符号链接（服务器 storage:link 重建；本地需要时再 php artisan storage:link）"
  rm -f public/storage
fi

# ---------- 1.3 选择 tar 实现 ----------
# GNU tar 能正确处理符号链接；bsdtar 遇死链会中断。优先 GNU tar。
TAR="tar"
for candidate in /usr/bin/tar tar; do
  if command -v "$candidate" >/dev/null 2>&1 \
    && "$candidate" --version 2>/dev/null | grep -qi 'GNU tar'; then
    TAR="$candidate"
    break
  fi
done

# ---------- 2. 打包 ----------
# 排除项说明:
#   vendor/        -> 服务器 composer install 生成
#   node_modules/  -> 服务器 npm install 生成
#   .git/          -> 版本库
#   .workbuddy/    -> AI 工具本地数据
#   .env           -> 含密钥，服务器单独配置
#   .venv_tools/ .yarn/ .idea/ .vscode/ -> 本地工具目录
#   import/images/ -> 一次性素材图（12M，运行时用不到）
#   import/*.py import/*.js -> 一次性导入脚本
#   storage/framework/{views,cache,sessions} -> 运行时缓存
#   storage/logs/  -> 日志
#   public/hot     -> Vite dev server 标记
#   public/storage -> 符号链接，服务器用 php artisan storage:link 重建
# 保留项(重要):
#   public/images/       -> 食谱图片，image_src 优先读本地
#   import/recipes_full.json -> RecipesTableSeeder 数据源
#   public/build/        -> 已构建的前端产物(服务器无 node 也可直接用)
echo "==> 打包中 (排除 vendor / node_modules / .git / .workbuddy / .env ...)"
echo "    使用 tar: $TAR"
"$TAR" -czf "$OUT_DIR/$PKG" \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./.git' \
  --exclude='./.workbuddy' \
  --exclude='./.venv_tools' \
  --exclude='./.yarn' \
  --exclude='./.idea' \
  --exclude='./.vscode' \
  --exclude='./.env' \
  --exclude='./.env.backup' \
  --exclude='./.env.production' \
  --exclude='./.phpunit.result.cache' \
  --exclude='./import/images' \
  --exclude='./import/recipe_images' \
  --exclude='./import/*.py' \
  --exclude='./import/*.js' \
  --exclude='./storage/framework/testing' \
  --exclude='./storage/framework/hot' \
  --exclude='./storage/framework/views/*.php' \
  --exclude='./storage/framework/cache/data/*' \
  --exclude='./public/hot' \
  --exclude='./public/storage' \
  --exclude='./nginx/logs' \
  --exclude='./release' \
  --exclude='./*.tar.gz' \
  --exclude='./*.DS_Store' \
  .

# ---------- 3. 输出报告 ----------
SIZE=$(du -h "$OUT_DIR/$PKG" | cut -f1)
echo ""
echo "==> 打包完成"
echo "    文件: $OUT_DIR/$PKG"
echo "    大小: $SIZE"
echo ""
echo "==> 包内顶层目录:"
# 条目形如 ./app/ (3 段) 与 ./README.md (2 段)，深层文件段数更多，直接被过滤掉。
"$TAR" -tzf "$OUT_DIR/$PKG" | awk -F/ 'NF<=3 && $1!=""' | sort -u | head -40
echo ""
echo "==> 下一步: 上传该文件到服务器, 解压后执行 deploy/deploy.sh"
