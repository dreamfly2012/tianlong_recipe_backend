#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 服务器端部署脚本 (Linux / 宝塔 / Docker 均适用)
#  用法: bash deploy.sh
#  前提: 压缩包已解压到当前目录, 且当前目录就是项目根目录
# ============================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'; NC='\033[0m'
info() { echo -e "${GRN}==>${NC} $1"; }
warn() { echo -e "${YEL}[!]${NC} $1"; }
err()  { echo -e "${RED}[x]${NC} $1"; }

# ---------- 0. 环境检查 ----------
info "检查运行环境"
command -v php >/dev/null 2>&1 || { err "未找到 php，请先安装 PHP >= 8.2"; exit 1; }
command -v composer >/dev/null 2>&1 || { err "未找到 composer，请先安装 Composer 2"; exit 1; }
php -v | head -1
composer --version 2>/dev/null | head -1

# ---------- 0.1 PHP 扩展检查 ----------
# 必须在 composer install 之前检查，否则 composer 会报
# "requires ext-fileinfo * -> it is missing from your system"
info "检查 PHP 扩展"
MISSING_EXT=()
for ext in fileinfo mbstring pdo_mysql openssl curl json tokenizer; do
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    printf "  [OK] %s\n" "$ext"
  else
    printf "  [缺失] %s\n" "$ext"
    MISSING_EXT+=("$ext")
  fi
done

if [ ${#MISSING_EXT[@]} -gt 0 ]; then
  err "缺少 PHP 扩展: ${MISSING_EXT[*]}"
  echo ""
  echo "  【宝塔面板】软件商店 -> PHP -> 对应版本 -> 设置 -> 安装扩展:"
  echo "      fileinfo / mbstring / pdo_mysql"
  echo "      (fileinfo 勾选后需重启 PHP)"
  echo ""
  echo "  【Debian/Ubuntu】"
  echo "      apt-get update && apt-get install -y php${PHP_VER}-fileinfo php${PHP_VER}-mbstring"
  echo "      (PHP_VER 取 php -v 中的版本号，如 8.2)"
  echo ""
  echo "  【源码编译】需在 php 源码的 ext/fileinfo 目录下执行:"
  echo "      phpize && ./configure --with-php-config=/usr/bin/php-config && make && make install"
  echo "      然后在 php.ini 中添加: extension=fileinfo.so"
  echo ""
  echo "  【临时绕过】(不推荐，仅用于本地验证):"
  echo "      composer install --ignore-platform-req=ext-fileinfo"
  echo ""
  echo "  装好后用以下命令确认:"
  echo "      php -m | grep fileinfo"
  exit 1
fi

# ---------- 1. .env ----------
if [ ! -f .env ]; then
  info "生成 .env（从 .env.example 复制）"
  [ -f .env.example ] && cp .env.example .env || touch .env
  warn "已创建 .env，请手动填入数据库/COS/微信配置后重新运行本脚本"
  exit 0
fi

# ---------- 2. PHP 依赖 ----------
# 前置：确认 vendor 目录能创建。否则 composer 会报
# "/path/vendor does not exist and could not be created"
info "检查 vendor 目录可写性"
if ! mkdir -p vendor 2>/dev/null; then
  err "无法在 $APP_DIR 创建 vendor 目录"
  echo ""
  echo "  【原因 1】当前用户无权写入（宝塔默认站点属 www:www）"
  echo "      chown -R www:www $APP_DIR && chmod -R 755 $APP_DIR"
  echo "      chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache"
  echo "      # 或: sudo -u www composer install --no-dev"
  echo ""
  echo "  【原因 2】当前目录不是项目根目录（缺 composer.json）"
  echo "      ls -la $APP_DIR"
  echo "      find /www/wwwroot -maxdepth 3 -name composer.json 2>/dev/null"
  echo ""
  echo "  【原因 3】磁盘空间不足"
  echo "      df -h $APP_DIR"
  echo ""
  echo "  修复后可运行: bash deploy/fix-vendor.sh （自动诊断+修复）"
  exit 1
fi
rmdir vendor 2>/dev/null || true
ok "vendor 目录可创建"

# Composer 版本过旧（1.x 不支持 PHP8，2.2.x 在 PHP8.4+ 刷 deprecation 噪音）
CV=$(composer --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+' | head -1)
if [ -n "$CV" ]; then
  CV_MAJOR=${CV%%.*}
  CV_MINOR=${CV#*.}
  if [ "$CV_MAJOR" = "1" ]; then
    err "Composer $CV 不支持 PHP 8，请先升级: composer self-update --2"
    exit 1
  fi
  if [ "$CV_MAJOR" = "2" ] && [ "$CV_MINOR" -lt 5 ]; then
    warn "Composer $CV 版本偏旧（PHP 8.4+ 下会输出 deprecation 噪音）"
    echo "    升级: composer self-update --2"
  fi
fi

info "安装 PHP 依赖 (composer install)"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ---------- 3. 应用密钥 ----------
if ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
  info "生成 APP_KEY"
  php artisan key:generate --ansi
else
  info "APP_KEY 已存在，跳过"
fi

# ---------- 4. 目录权限 ----------
info "设置目录权限"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod -R 775 storage/framework/cache storage/framework/sessions storage/framework/views

# ---------- 5. 存储软链 ----------
if [ ! -L public/storage ]; then
  info "创建 storage 软链"
  rm -rf public/storage
  php artisan storage:link || warn "storage:link 失败，若已用 COS 可忽略"
fi

# ---------- 6. 清缓存 ----------
info "清理应用缓存"
php artisan config:clear
php artisan cache:clear || true
php artisan route:clear
php artisan view:clear

# ---------- 7. 数据库迁移 ----------
read -rp "是否执行数据库迁移 php artisan migrate --force? [y/N] " ANS
if [ "$ANS" = "y" ] || [ "$ANS" = "Y" ]; then
  info "执行数据库迁移"
  php artisan migrate --force
else
  warn "跳过 migrate（数据库需为服务器上已存在的库）"
fi

# ---------- 8. 生产优化 ----------
info "生产优化 (config/route/event 缓存)"
php artisan config:cache
php artisan route:cache
php artisan event:cache || true

# ---------- 9. 队列与计划任务 ----------
info "队列/计划任务提示："
echo "    - 若用队列: supervisor 管理 'php artisan queue:work'"
echo "    - 若用定时任务: crontab 加 '* * * * * cd $APP_DIR && php artisan schedule:run'"

# ---------- 10. 权限收尾 ----------
info "修正 public 与 storage 权限"
chmod -R 775 public/storage 2>/dev/null || true
chmod -R 775 public/images 2>/dev/null || true
chmod -R 775 storage 2>/dev/null || true

echo ""
echo -e "${GRN}========================================${NC}"
echo -e "${GRN}  部署完成${NC}"
echo -e "${GRN}========================================${NC}"
echo "  1. 确认 .env 中 APP_URL / DB_* / COS_* 正确"
echo "  2. Nginx 站点根目录指向: $APP_DIR/public"
echo "  3. 重启 PHP-FPM: systemctl restart php-fpm (或 php8.2-fpm)"
echo "  4. 访问健康检查: curl -I http://<你的域名>/api/recipe.lists"
