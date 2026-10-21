#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 修复 "vendor does not exist and could not be created"
#  用法: bash deploy/fix-vendor.sh
#
#  典型报错:
#    /www/wwwroot/recipe.80shihua.com/vendor does not exist and could not be created
#    JsonSchema\Constraints\EnumConstraint::check(): Implicitly marking parameter
#    $path as nullable is deprecated, ...
#
#  注意: 报错末尾那段 JsonSchema/E_STRICT 的 deprecation 是 PHP 8.4+ 的噪音，
#        不是失败原因。真正原因只有一句: vendor 目录建不出来。
# ============================================================
set -uo pipefail

RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'; BLU='\033[0;36m'; NC='\033[0m'
ok()   { echo -e "${GRN}[OK]${NC} $1"; }
bad()  { echo -e "${RED}[X]${NC} $1"; }
warn() { echo -e "${YEL}[!]${NC} $1"; }
info() { echo -e "${BLU}==${NC} $1 ${BLU}==${NC}"; }
err()  { echo -e "${RED}[x]${NC} $1"; }

# 用法: bash deploy/fix-vendor.sh [项目目录] [--dry-run]
DRY_RUN=0
TARGET=""
for a in "$@"; do
  case "$a" in
    --dry-run) DRY_RUN=1 ;;
    -*) echo "未知参数: $a"; exit 1 ;;
    *) TARGET="$a" ;;
  esac
done
APP_DIR="${TARGET:-$PWD}"
cd "$APP_DIR" 2>/dev/null || { err "目录不存在: $APP_DIR"; exit 1; }

echo "============================================"
echo "  修复 vendor 目录创建失败"
echo "  当前目录: $(pwd)"
echo "  执行用户: $(whoami)  (uid=$(id -u))"
echo "============================================"

# ---------- 1. 定位真实问题 ----------
info "1. 诊断"
echo "  目录权限: $(stat -c '%A %U:%G' . 2>/dev/null || stat -f '%Sp %Su:%Sg' . 2>/dev/null)"
echo "  父目录  : $(stat -c '%A %U:%G' .. 2>/dev/null || stat -f '%Sp %Su:%Sg' .. 2>/dev/null)"

# 磁盘空间（统一换算成 MB 再比较）
AVAIL_RAW=$(df -Pm . 2>/dev/null | awk 'NR==2{print $4}')
if [ -n "$AVAIL_RAW" ]; then
  AVAIL_MB=$(echo "$AVAIL_RAW" | tr -dc '0-9')
  echo "  磁盘可用: ${AVAIL_MB}MB"
  if [ "${AVAIL_MB:-0}" -lt 500 ]; then
    bad "磁盘可用空间仅 ${AVAIL_MB}MB (<500MB)，composer 安装必然失败！"
    echo "      清理: rm -rf /www/server/panel/pyenv/cache/* /tmp/*"
    echo "      或: df -h  查看哪个分区满了"
  else
    ok "磁盘空间充足"
  fi
fi

# 能否创建目录
if mkdir -p vendor 2>/dev/null; then
  ok "vendor 目录创建成功（权限正常）"
  rmdir vendor 2>/dev/null || true
  CAN_CREATE=1
else
  bad "无法在当前目录创建 vendor —— 这就是报错根因"
  CAN_CREATE=0
fi

# ---------- 2. 针对性修复 ----------
info "2. 尝试修复"

# 情形 A: 以 root 运行但目录属于 www（宝塔默认）
if [ "$CAN_CREATE" = "0" ] && [ "$(id -u)" = "0" ]; then
  warn "当前是 root 但无法创建目录，检查是否存在不可写挂载点或 SELinux"
  if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce 2>/dev/null)" = "Enforcing" ]; then
    warn "检测到 SELinux Enforcing，需要放行目录"
    echo "      setsebool -P httpd_read_user_content 1"
    echo "      chcon -R -t httpd_sys_content_t $APP_DIR"
  fi
  if mount | grep -q "on $(df --output=target . 2>/dev/null | tail -1) .*ro,"; then
    bad "所在分区是只读挂载，无法写入"
  fi
fi

# 情形 B: 目录属于 www，当前是普通用户
OWNER=$(stat -c '%U' . 2>/dev/null || stat -f '%Su' . 2>/dev/null)
GROUP=$(stat -c '%G' . 2>/dev/null || stat -f '%Sg' . 2>/dev/null)
if [ "$CAN_CREATE" = "0" ] && [ -n "$OWNER" ] && [ "$OWNER" != "$(whoami)" ]; then
  warn "目录属于 ${OWNER}:${GROUP}，当前用户是 $(whoami)"
  echo ""
  echo "  >>> 推荐做法 (宝塔标准权限) <<<"
  echo "      # 1) 站点目录交给 www 用户（与 PHP-FPM 运行用户一致）"
  echo "      chown -R www:www $APP_DIR"
  echo "      chmod -R 755 $APP_DIR"
  echo "      chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache"
  echo ""
  echo "      # 2) 或用 www 用户跑 composer"
  echo "      sudo -u www composer install --no-dev --prefer-dist --optimize-autoloader"
  echo ""
  echo "  >>> 临时办法: 换个能写的位置装依赖 <<<"
  echo "      composer install --no-dev --prefer-dist -d ."
  echo ""
fi

# 情形 C: 路径根本不存在（最常见 —— 解压到了别的目录）
if [ ! -f "composer.json" ]; then
  bad "当前目录没有 composer.json —— 你可能不在项目根目录！"
  echo ""
  echo "  >>> 排查 <<<"
  echo "      ls -la              # 看看当前目录有什么"
  echo "      find /www/wwwroot -maxdepth 3 -name composer.json 2>/dev/null"
  echo ""
  echo "  找到项目根目录后 cd 过去再跑本脚本，例如:"
  echo "      cd /www/wwwroot/recipe.80shihua.com && bash deploy/fix-vendor.sh"
  echo ""
  echo "  >>> 如果是解压时多套了一层目录 <<<"
  echo "      ls -d /www/wwwroot/recipe.80shihua.com/*/ | head"
  echo "      # 若看到 tianlong-recipe-20261021-xxxx/ 这样的子目录，说明多了一层："
  echo "      cd /www/wwwroot/recipe.80shihua.com/tianlong-recipe-*/"
  echo "      # 或者把它们移上来："
  echo "      mv /www/wwwroot/recipe.80shihua.com/tianlong-recipe-*/{app,bootstrap,config,composer.json,composer.lock,routes,storage,public,deploy} /www/wwwroot/recipe.80shihua.com/"
  exit 1
fi

ok "composer.json 存在，项目根目录正确"

# ---------- 3. Composer 版本与 PHP 兼容性 ----------
info "3. Composer / PHP 版本"
PHPV=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
ok "PHP: $PHPV"

CV=$(composer --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
if [ -n "$CV" ]; then
  ok "Composer: $CV"
  # 旧版 composer(2.2.x)在 PHP 8.4+ 会刷 deprecation 噪音
  MAJOR=${CV%%.*}
  REST=${CV#*.}
  MINOR=${REST%%.*}
  if [ "$MAJOR" = "2" ] && [ "$MINOR" -lt 5 ]; then
    warn "Composer $CV 版本偏旧，在 PHP 8.4+ 下会输出大量 deprecation 噪音"
    echo "      升级: composer self-update --2"
    echo "      或:  php -r \"copy('https://getcomposer.org/installer','composer-setup.php');\" && php composer-setup.php --2 && rm composer-setup.php"
  fi
  if [ "$MAJOR" = "1" ]; then
    bad "Composer 1.x 不支持 PHP 8，必须升级到 2.x"
    echo "      composer self-update --2"
  fi
else
  warn "无法获取 composer 版本"
fi

# ---------- 4. 正式安装 ----------
if [ "$DRY_RUN" = "1" ]; then
  info "4. 跳过安装 (--dry-run)"
  echo "  诊断完成。以上为将要执行的操作预览。"
  echo "  去掉 --dry-run 即可正式安装依赖。"
  exit 0
fi

info "4. 安装依赖"
if [ "$CAN_CREATE" = "0" ]; then
  err "vendor 目录仍无法创建，请先按上面的提示修复权限或切换到正确目录"
  exit 1
fi

echo "  执行: composer install --no-dev --prefer-dist --optimize-autoloader"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
RC=$?

if [ $RC -ne 0 ]; then
  err "composer install 失败 (exit $RC)"
  echo ""
  echo "  >>> 若仍报 ext-fileinfo 缺失 <<<"
  echo "      fileinfo 是 PHP 扩展，Composer 只认「运行 Composer 的那个 PHP」"
  echo "      宝塔上常见的坑: 终端里的 php 是 8.2，而站点跑的是 8.3/8.4"
  echo "      临时绕过: composer install --no-dev --ignore-platform-req=ext-fileinfo"
  echo ""
  echo "  >>> 若报权限/网络问题 <<<"
  echo "      composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/"
  exit $RC
fi

ok "依赖安装完成"

# ---------- 5. 收尾权限 ----------
info "5. 修正权限"
if [ "$(id -u)" = "0" ] && id www >/dev/null 2>&1; then
  chown -R www:www vendor 2>/dev/null && ok "vendor 归属改为 www:www" || warn "chown www 失败"
fi
chmod -R 755 vendor 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
ok "权限修正完成"

echo ""
echo "============================================"
echo "  修复流程结束，继续执行部署:"
echo "    cd $APP_DIR && bash deploy/deploy.sh"
echo "============================================"
