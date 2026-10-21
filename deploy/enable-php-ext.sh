#!/usr/bin/env bash
# ============================================================
#  启用 scoop PHP 的必要扩展 (Windows / Git Bash)
#  用法: bash deploy/enable-php-ext.sh [php.ini 路径]
#  默认: 自动探测当前 php 实际加载的 php.ini
#
#  背景: scoop 的 php84 生成的 php.ini 里所有 extension 默认都是注释状态
#        (30 个 ;extension=xxx, 0 个启用)，导致 composer 报
#        "The openssl extension is required for SSL/TLS protection"
#        以及 "requires ext-fileinfo * -> it is missing"
#
#  注意: 本机有多个 PHP (scoop php=8.5 / php84=8.4)，
#        扩展 dll 必须与 php.ini 属于同一个安装目录，不能混用。
# ============================================================
set -uo pipefail

RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'; BLU='\033[0;36m'; NC='\033[0m'
ok()   { echo -e "${GRN}[OK]${NC} $1"; }
bad()  { echo -e "${RED}[X]${NC} $1"; }
warn() { echo -e "${YEL}[!]${NC} $1"; }
info() { echo -e "${BLU}==${NC} $1 ${BLU}==${NC}"; }
err()  { echo -e "${RED}[x]${NC} $1"; }

# ---------- 1. 定位 php.ini ----------
INI="${1:-}"
if [ -z "$INI" ]; then
  if ! command -v php >/dev/null 2>&1; then
    err "未找到 php 命令"
    exit 1
  fi
  INI=$(php -i 2>/dev/null | grep -i "Loaded Configuration File" | head -1 | sed 's/.*=> //' | tr -d '\r')
fi

if [ -z "$INI" ] || [ ! -f "$INI" ]; then
  err "无法定位 php.ini: $INI"
  echo ""
  echo "  手动指定 (Windows 路径):"
  echo "    bash deploy/enable-php-ext.sh 'C:\\Users\\fujia\\scoop\\apps\\php84\\current\\php.ini'"
  exit 1
fi

# 把 Windows 路径转成 unix 路径方便操作
INI_U=$(cygpath -u "$INI" 2>/dev/null || echo "$INI")
INI_DIR=$(dirname "$INI_U")

echo "============================================"
echo "  启用 PHP 扩展"
echo "  php.ini : $INI"
echo "  所在目录: $INI_DIR"
echo "  PHP     : $(php -v 2>/dev/null | head -1)"
echo "============================================"

# ---------- 2. 确定扩展 DLL 目录 ----------
# 关键: 必须用 php.ini 所在目录的 ext/，而不是 PATH 上任意 php 的 ext/
# 本机装了 scoop php(8.5) 和 php84(8.4) 两个版本，
# ini 里的 extension_dir="ext" 是相对路径，PHP 按【当前工作目录】解析，
# 因此在项目目录跑 php 时会误加载 8.5 的 dll，导致 ABI 版本冲突。
info "1. 定位扩展目录"
EXT_ABS=""
for cand in "$INI_DIR/ext" "$INI_DIR"; do
  if [ -d "$cand" ] && ls "$cand"/php_openssl.dll >/dev/null 2>&1; then
    EXT_ABS="$cand"
    break
  fi
done
if [ -z "$EXT_ABS" ]; then
  err "找不到与 php.ini 同级的扩展目录 ($INI_DIR/ext)"
  echo ""
  echo "  可用 PHP 安装:"
  ls -d /c/Users/fujia/scoop/apps/php*/current 2>/dev/null
  echo ""
  echo "  若 ini 与 ext 不在同一目录，请手动指定: bash deploy/enable-php-ext.sh <php.ini>"
  exit 1
fi
ok "扩展目录: $EXT_ABS"

# 校验：这个 ext 目录与 ini 是否同源
if ! ls "$EXT_ABS/php_openssl.dll" >/dev/null 2>&1; then
  bad "扩展目录与 php.ini 不匹配（缺 php_openssl.dll）"
  echo "    期望: $INI_DIR/ext/php_openssl.dll"
  echo "    实际: $EXT_ABS"
  exit 1
fi

# ABI 版本校验：dll 的 API 版本必须与当前 PHP 一致
PHP_API=$(php -r 'echo PHP_MAJOR_VERSION*10000000+PHP_MINOR_VERSION*100000+PHP_RELEASE_VERSION;' 2>/dev/null)
echo "  PHP ABI : $PHP_API"

# ---------- 3. 需要启用的扩展清单 ----------
# pdo_sqlite 必须启用: phpunit.xml 用 SQLite :memory:，缺它会报
# "could not find driver (Connection: sqlite)"
NEED_EXT=(openssl fileinfo mbstring pdo_mysql pdo_sqlite curl zip gd exif intl sqlite3 opcache)
# Windows 上这些编译进核心，无需单独启用
BUILTIN=(tokenizer json pcre date filter hash iconv libxml mysqlnd pcre PDO Phar random readline Reflection session SimpleXML SPL standard xml xmlreader xmlwriter zlib calendar ctype)

info "2. 检查扩展文件"
ENABLE_LIST=()
for ext in "${NEED_EXT[@]}"; do
  # 已内置
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    ok "$ext  -> 已内置/已加载"
    continue
  fi

  dll=""
  for cand in "$EXT_ABS/php_${ext}.dll" "$EXT_ABS/${ext}.dll"; do
    [ -f "$cand" ] && { dll="$cand"; break; }
  done
  if [ -z "$dll" ]; then
    warn "$ext  -> 未找到扩展文件，跳过"
    continue
  fi

  # ini 中是否已启用
  if tr -d '\r' < "$INI_U" | grep -qE "^[[:space:]]*extension=${ext}(\.dll)?[[:space:]]*$"; then
    ok "$ext  -> ini 中已启用"
    continue
  fi

  ENABLE_LIST+=("$ext")
  echo "  -> 待启用: $ext  ($(basename "$dll"))"
done

if [ ${#ENABLE_LIST[@]} -eq 0 ]; then
  ok "所有需要的扩展均已启用，无需修改"
  exit 0
fi

# ---------- 4. 备份 + 写入 ----------
info "3. 备份并写入 php.ini"
BACKUP="${INI}.bak.$(date +%Y%m%d-%H%M%S)"
cp "$INI_U" "$BACKUP" && ok "备份: $BACKUP" || { err "备份失败"; exit 1; }

# 用 PHP 自身改写 ini，避开 sed 在 Windows 路径上的转义坑
PHP_BIN_EXE=""
for p in "$INI_DIR/php.exe" "$(command -v php 2>/dev/null)"; do
  [ -n "$p" ] && [ -f "$p" ] && { PHP_BIN_EXE="$p"; break; }
done
[ -z "$PHP_BIN_EXE" ] && PHP_BIN_EXE="php"

# 关键: 用【绝对路径】指定每个扩展 dll。
# 原因: ini 里的 extension_dir = "ext" 是相对路径，PHP 解析时基于【当前工作目录】
#       而非安装目录。本机有 php(8.5) 和 php84(8.4) 两个版本，
#       一旦 cwd 指向别处，就会加载到错误版本的 dll，
#       报 "Module compiled with module API=20250925, PHP compiled with 20240924"。
EXT_ABS_WIN=$(cygpath -w "$EXT_ABS" 2>/dev/null || echo "$EXT_ABS")
EXT_LIST="$(IFS=,; echo "${ENABLE_LIST[*]}")"

"$PHP_BIN_EXE" -r '
$ini     = $argv[1];
$exts    = array_filter(explode(",", $argv[2]));
$extDir  = rtrim($argv[3], "\\/");
$lines   = file($ini, FILE_IGNORE_NEW_LINES);
if ($lines === false) { fwrite(STDERR, "read fail\n"); exit(1); }
$done = [];
foreach ($exts as $ext) {
    $dll = $extDir . "\\php_" . $ext . ".dll";
    // opcache 是 Zend 扩展，必须用 zend_extension= 而非 extension=
    $isZend = ($ext === "opcache");
    $keyword = $isZend ? "zend_extension" : "extension";
    $hit = false;
    $dupIdx = [];
    foreach ($lines as $i => $l) {
        // 匹配任意写法的该扩展行（不带 php_ 前缀、带前缀、带 .dll、带绝对路径、带注释）
        // 注意: 用 (?:php_)? 兼容 php_opcache.dll 这类带前缀的文件名
        $re = "/^;?\s*(?:zend_)?extension\s*=\s*.*?(?:\\\\)?(?:php_)?{$ext}(?:\.dll)?\s*$/i";
        if (preg_match($re, $l)) {
            if ($hit) {
                // 重复行：先记录，稍后删除（PHP 报 "already loaded" 会导致加载失败）
                $dupIdx[] = $i;
            } else {
                $lines[$i] = $keyword . "=" . $dll;
                $hit = true;
            }
        }
    }
    // 删除重复行（倒序删除，避免索引错乱）
    if (!empty($dupIdx)) {
        rsort($dupIdx);
        foreach ($dupIdx as $di) { array_splice($lines, $di, 1); }
    }
    if (!$hit) { $lines[] = $keyword . "=" . $dll; }
    $done[] = $ext;
}
$out = implode("\r\n", $lines) . "\r\n";
file_put_contents($ini, $out);
echo "written: " . implode(",", $done) . "\n";
' "$INI_U" "$EXT_LIST" "$EXT_ABS_WIN" || { err "写入 php.ini 失败"; exit 1; }

ok "php.ini 已更新（使用绝对路径指定 dll）"

# ---------- 5. 验证 ----------
info "4. 验证"
FAIL=()
for ext in "${ENABLE_LIST[@]}"; do
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    ok "$ext 已加载"
  elif [ "$ext" = "opcache" ] && php -i 2>/dev/null | grep -qi "Zend OPcache"; then
    # opcache 是 Zend 扩展，CLI 下 opcache.enable_cli=Off 时 php -m 不显示，但实际已加载
    ok "opcache 已加载 (Zend 扩展, CLI 下 enable_cli=Off 故 php -m 不显示)"
  else
    bad "$ext 仍未加载"
    FAIL+=("$ext")
  fi
done

if [ ${#FAIL[@]} -gt 0 ]; then
  echo ""
  warn "以下扩展未能加载: ${FAIL[*]}"
  echo ""
  echo "  常见原因: 依赖的 dll 不在 PATH"
  echo "    需要: libssl-3-x64.dll / libcrypto-3-x64.dll / zlib1.dll 等"
  echo "    解决: 把 $INI_DIR 加入系统 PATH，然后重开终端"
  echo ""
  echo "  另一种可能: ini 中 extension_dir 与实际不符"
  echo "    确认: php -i | grep -i extension_dir"
  echo ""
  echo "  终极方案: 用 docker compose 起 PHP 环境（不依赖本机 PHP 配置）"
  echo "    docker compose up -d"
fi

echo ""
echo "============================================"
echo "  完成"
echo "============================================"
if [ ${#FAIL[@]} -eq 0 ]; then
  echo "  现在可以执行: composer install"
else
  echo "  若仍失败，把上面报错贴出来，或改用 docker compose 部署"
fi
