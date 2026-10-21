#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 生产部署打包脚本
#  用法: bash deploy/pack.sh [输出目录]
#  产物: tianlong-recipe-YYYYmmdd-HHMMSS.tar.gz
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/release}"
STAMP="$(date +%Y%m%d-%H%M%S)"
PKG="tianlong-recipe-${STAMP}.tar.gz"

mkdir -p "$OUT_DIR"
cd "$ROOT"

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
tar -czf "$OUT_DIR/$PKG" \
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
tar -tzf "$OUT_DIR/$PKG" | awk -F/ 'NF<=2 && $1!=""' | sort -u | head -40
echo ""
echo "==> 下一步: 上传该文件到服务器, 解压后执行 deploy/deploy.sh"
