#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 服务器环境诊断脚本
#  用法: bash deploy/check-env.sh
#  作用: 检查 PHP 版本/扩展/composer/目录权限，定位 composer install 失败原因
# ============================================================
set -uo pipefail

RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'; NC='\033[0m'
ok()   { echo -e "${GRN}[OK]${NC} $1"; }
bad()  { echo -e "${RED}[X]${NC} $1"; }
warn() { echo -e "${YEL}[!]${NC} $1"; }
info() { echo -e "\n${GRN}==${NC} $1 ${GRN}==${NC}"; }

echo "============================================"
echo "  天龙食谱 服务器环境诊断"
echo "  生成时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================"

# ---------- 1. PHP ----------
info "1. PHP 基本信息"
if command -v php >/dev/null 2>&1; then
  ok "PHP 路径: $(command -v php)"
  php -v | head -2
  echo "  ini 文件: $(php -i 2>/dev/null | grep -i 'Loaded Configuration File' | head -1 | sed 's/.*=> //')"
  echo "  扫描目录: $(php -i 2>/dev/null | grep -i '^Scan this dir' | sed 's/.*=> //')"
else
  bad "未找到 php 命令"
  exit 1
fi

# ---------- 2. 扩展 ----------
info "2. 扩展检查"
NEED_EXT=(fileinfo mbstring pdo_mysql openssl curl json tokenizer dom xml simplexml iconv zip gd bcmath)
FAILED=()
for ext in "${NEED_EXT[@]}"; do
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    printf "  %s %-12s %s\n" "$GRN[OK]$NC" "$ext" "$(php -r "echo phpversion('$ext') ?: '';" 2>/dev/null)"
  else
    printf "  %s %-12s %s\n" "$RED[X]$NC" "$ext" "缺失"
    FAILED+=("$ext")
  fi
done

if [ ${#FAILED[@]} -gt 0 ]; then
  echo ""
  bad "缺失扩展: ${FAILED[*]}"
  echo ""
  echo "  >>> 关键: fileinfo 是 composer install 失败的直接原因 <<<"
  echo ""
  echo "  修复方式（按你的服务器环境选一种）:"
  echo ""
  echo "  【宝塔面板】软件商店 -> PHP -> 版本 -> 设置 -> 安装扩展"
  echo "      搜索并安装: fileinfo"
  echo "      然后: 重启 PHP 服务"
  echo ""
  echo "  【Debian / Ubuntu / 树莓派】"
  echo "      apt-get update"
  echo "      apt-get install -y php-fileinfo php-mbstring"
  echo "      systemctl restart php-fpm  (或 php8.2-fpm)"
  echo ""
  echo "  【CentOS / Rocky / AlmaLinux】"
  echo "      yum install -y php-fileinfo php-mbstring"
  echo "      systemctl restart php-fpm"
  echo ""
  echo "  【源码编译安装的 PHP】"
  echo "      cd /usr/local/src/php/ext/fileinfo"
  echo "      phpize && ./configure --with-php-config=/usr/local/bin/php-config"
  echo "      make && make install"
  echo "      echo 'extension=fileinfo.so' >> /usr/local/etc/php/conf.d/xx.ini"
  echo "      systemctl restart php-fpm"
  echo ""
  echo "  【不想装扩展时的临时绕过】(不推荐，图片上传/类型判断可能异常)"
  echo "      composer install --ignore-platform-req=ext-fileinfo"
  echo ""
  echo "  验证: php -m | grep fileinfo"
else
  ok "所有扩展齐备"
fi

# ---------- 3. Composer ----------
info "3. Composer"
if command -v composer >/dev/null 2>&1; then
  ok "$(composer --version 2>/dev/null | head -1)"
  echo "  源配置: $(composer config -g repo.packagist 2>/dev/null || echo '未设置')"
else
  bad "未找到 composer"
  echo "  安装: curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
fi

# ---------- 4. 项目结构 ----------
info "4. 项目目录"
CWD="$(pwd)"
echo "  当前目录: $CWD"
for d in app bootstrap config database public resources routes storage vendor .env composer.json composer.lock; do
  if [ -e "$d" ]; then
    ok "$d"
  else
    [ "$d" = "vendor" ] && warn "vendor  (需执行 composer install)" && continue
    [ "$d" = ".env" ] && warn ".env    (需从 .env.example 复制并配置)" && continue
    bad "$d  缺失"
  fi
done

# ---------- 4.1 vendor 目录可创建性 ----------
info "4.1 vendor 目录可创建性 (本次报错的根因)"
if mkdir -p vendor 2>/dev/null; then
  ok "可创建 vendor 目录"
  rmdir vendor 2>/dev/null || true
else
  bad "无法创建 vendor 目录 —— composer install 必然失败"
  echo "  目录属主: $(stat -c '%A %U:%G' . 2>/dev/null || stat -f '%Sp %Su:%Sg' . 2>/dev/null)"
  echo "  当前用户: $(whoami)"
  echo "  修复:"
  echo "    chown -R www:www . && chmod -R 755 ."
  echo "    chmod -R 775 storage bootstrap/cache"
fi
AVAIL=$(df -Pm . 2>/dev/null | awk 'NR==2{print $4}')
if [ -n "$AVAIL" ]; then
  echo "  磁盘可用: ${AVAIL}MB"
  AVAIL_NUM=$(echo "$AVAIL" | tr -dc '0-9')
  if [ "${AVAIL_NUM:-0}" -lt 500 ]; then
    bad "磁盘可用空间不足 500MB，composer install 会失败"
  fi
fi

# ---------- 5. 关键文件 ----------
info "5. 关键文件"
for f in public/build/manifest.json import/recipes_full.json public/images; do
  if [ -e "$f" ]; then
    if [ -d "$f" ]; then
      ok "$f  (图片 $(ls "$f" 2>/dev/null | wc -l) 张)"
    else
      ok "$f"
    fi
  else
    bad "$f 缺失"
  fi
done

# ---------- 6. 权限 ----------
info "6. 目录权限"
for d in storage storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  if [ -d "$d" ]; then
    if [ -w "$d" ]; then
      ok "$d  可写"
    else
      bad "$d  不可写 -> chmod -R 775 $d"
    fi
  else
    bad "$d 不存在 -> mkdir -p $d && chmod -R 775 $d"
  fi
done

# ---------- 7. 数据库连通性 ----------
info "7. 数据库连通性"
if [ -f .env ]; then
  DBH=$(grep -E '^DB_HOST=' .env | cut -d= -f2- | tr -d '\r"')
  DBP=$(grep -E '^DB_PORT=' .env | cut -d= -f2- | tr -d '\r"')
  DBN=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '\r"')
  echo "  配置: ${DBH}:${DBP}/${DBN}"
  if command -v mysql >/dev/null 2>&1; then
    if mysql -h "$DBH" -P "$DBP" -u root -e "SELECT 1" >/dev/null 2>&1; then
      ok "MySQL 可连接 (root)"
    else
      warn "MySQL 连接失败，检查 DB_HOST/DB_USERNAME/DB_PASSWORD 与防火墙"
    fi
  else
    warn "无 mysql 客户端，可跳过"
  fi
else
  warn "无 .env，跳过数据库检查"
fi

echo ""
echo "============================================"
echo "  诊断结束"
echo "============================================"
echo ""
echo "常见问题速查:"
echo "  A) composer 报 ext-fileinfo 缺失"
echo "     -> 装 fileinfo 扩展(见上方), 或用 --ignore-platform-req=ext-fileinfo 绕过"
echo ""
echo "  B) composer 报版本锁定冲突"
echo "     -> composer install --no-dev (跳过 dev 依赖) 或 composer update --lock"
echo ""
echo "  C) 提示 could not find driver (PDO)"
echo "     -> 装 pdo_mysql 扩展"
echo ""
echo "  D) 前端页面 404 / 资源加载失败"
echo "     -> 确认 public/build 存在, 且 Nginx 根目录指向 public"
