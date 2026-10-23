#!/usr/bin/env bash
# ============================================================
#  天龙食谱 - 生产部署打包脚本
#  用法: bash deploy/pack.sh [输出目录] [--upload]
#  产物: tianlong-recipe-YYYYmmdd-HHMMSS.tar.gz
#  --upload: 打包后通过 FTP 上传到服务器。
#            地址/密码配置在 deploy/ftp.env（已被 .gitignore 忽略，
#            也不会打进发布包，不会泄露到 git）。
# ============================================================
set -euo pipefail

# ---------- 0. 修正 PATH：优先使用 bash 自带的 coreutils ----------
# 从 cmd/PowerShell 启动 bash（非登录 shell）时，PATH 里 C:\Windows\System32
# 可能排在 Git 的 /usr/bin 之前：sort/find 会命中 Windows 同名程序
# （sort.exe 不支持 -u），尾部的目录清单管道报 "The system cannot find the
# file specified."，pipefail 下整个脚本中止。用 awk（Windows 无同名程序）
# 定位 coreutils 目录并提到 PATH 最前，一次修好 sort / find / head 等调用。
if AWK_BIN="$(command -v awk 2>/dev/null)"; then
  COREUTILS_DIR="${AWK_BIN%/*}"
  case "$COREUTILS_DIR" in
    /*)
      case "$PATH" in
        "$COREUTILS_DIR":*) ;;
        *) PATH="$COREUTILS_DIR:$PATH" ;;
      esac
      ;;
  esac
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# 输出目录一律先切到项目根再解析，相对路径参数（如 release）始终以仓库根为基准；
# 反斜杠写法 .\release\ 会被解析成奇怪的目录，导致产物落在别处甚至根目录。
OUT_DIR="release"
UPLOAD=0
for arg in "$@"; do
  case "$arg" in
    --upload) UPLOAD=1 ;;
    *) OUT_DIR="$arg" ;;
  esac
done
mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

if [ "$OUT_DIR" = "$ROOT" ]; then
  echo "!! 输出目录不能是项目根目录（tar 会把产物打进包里），请用: bash deploy/pack.sh release" >&2
  exit 1
fi

# ---------- 0.1 FTP 上传配置预检（--upload 时生效，配置错就不白打包） ----------
FTP_ENV="$ROOT/deploy/ftp.env"
if [ "$UPLOAD" -eq 1 ]; then
  if [ ! -f "$FTP_ENV" ]; then
    echo "!! 未找到 deploy/ftp.env，无法上传" >&2
    echo "   先执行: cp deploy/ftp.env.example deploy/ftp.env 并填写服务器信息" >&2
    echo "   （该文件已被 .gitignore 忽略，也不会被打进发布包，不会泄露到 git）" >&2
    exit 1
  fi
  command -v curl >/dev/null 2>&1 || { echo "!! FTP 上传依赖 curl，但本机未安装 curl" >&2; exit 1; }
  # 只按 KEY=VALUE 原样取值（不 source/不 eval），密码里的 $ ` " # 空格等字符都安全；
  # tr 去 \r 是为了兼容 Windows 记事本保存的 CRLF 换行。
  ftp_val() { sed -n "s/^$1=//p" "$FTP_ENV" | head -n 1 | tr -d '\r'; }
  FTP_HOST="$(ftp_val FTP_HOST)"
  FTP_PORT="$(ftp_val FTP_PORT)"
  FTP_USER="$(ftp_val FTP_USER)"
  FTP_PASS="$(ftp_val FTP_PASS)"
  FTP_DIR="$(ftp_val FTP_DIR)"
  FTP_SSL="$(ftp_val FTP_SSL)"
  FTP_PORT="${FTP_PORT:-21}"
  FTP_SSL="${FTP_SSL:-0}"
  [ -z "$FTP_DIR" ] && FTP_DIR="/"
  for v in FTP_HOST FTP_USER FTP_PASS; do
    if [ -z "${!v}" ]; then
      echo "!! deploy/ftp.env 缺少 $v 配置" >&2
      exit 1
    fi
  done
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
#   deploy/ftp.env -> FTP 上传凭据（含服务器地址/密码，严禁入包）
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
  --exclude='./deploy/ftp.env' \
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
# 仅信息展示：head 提前关闭会让上游收到 SIGPIPE，pipefail 下会被当成失败，故兜底。
"$TAR" -tzf "$OUT_DIR/$PKG" | awk -F/ 'NF<=3 && $1!=""' | sort -u | head -40 || true
echo ""
# ---------- 4. FTP 上传（--upload 开启时执行） ----------
# 凭据只出现在 deploy/ftp.env 与临时 curl 配置文件里：
# 不进 git（.gitignore）、不进发布包（tar 排除）、不进命令行（进程列表看不到密码）。
if [ "$UPLOAD" -eq 1 ]; then
  # 目标路径：FTP_DIR 为空或 "/" 时直接落在登录根目录，避免拼出 //文件名
  REMOTE_DIR="${FTP_DIR%/}"
  case "$REMOTE_DIR" in
    ""|"/") REMOTE_PATH="/$PKG" ;;
    /*)     REMOTE_PATH="$REMOTE_DIR/$PKG" ;;
    *)      REMOTE_PATH="/$REMOTE_DIR/$PKG" ;;
  esac

  # 密码写入临时 curl 配置文件传递（避免 -u 出现在命令行被 ps 看到），
  # 文件名含 mktemp 随机串，脚本退出时由 trap 清理。
  CURL_CONF="$(mktemp)"
  trap 'rm -f "$CURL_CONF"' EXIT
  CURL_USER="${FTP_USER//\\/\\\\}"; CURL_USER="${CURL_USER//\"/\\\"}"
  CURL_PASS="${FTP_PASS//\\/\\\\}"; CURL_PASS="${CURL_PASS//\"/\\\"}"
  printf -- '--user "%s:%s"\n' "$CURL_USER" "$CURL_PASS" > "$CURL_CONF"

  SSL_ARGS=()
  if [ "$FTP_SSL" = "1" ]; then SSL_ARGS=(--ssl-reqd); fi

  echo "==> FTP 上传: ftp://$FTP_HOST:$FTP_PORT$REMOTE_PATH"
  if curl --silent --show-error --fail --progress-bar \
       --ftp-create-dirs \
       "${SSL_ARGS[@]}" \
       --config "$CURL_CONF" \
       --upload-file "$OUT_DIR/$PKG" \
       "ftp://$FTP_HOST:$FTP_PORT$REMOTE_PATH"; then
    rm -f "$CURL_CONF"
    echo "==> 上传完成: $REMOTE_PATH"
    echo "==> 下一步: 在服务器解压该包后执行 deploy/deploy.sh"
  else
    RC=$?
    echo "!! FTP 上传失败（curl 退出码 $RC；核对 deploy/ftp.env、防火墙/IP 白名单与账号权限）" >&2
    if [ "$RC" = "25" ]; then
      echo "   553 通常是目标目录不可写：在服务器把站点根目录属主改成可写用户，例如" >&2
      echo "   chown \"\$(stat -c '%u:%g' storage)\" <站点根目录>；或把 FTP_DIR 指向可写子目录" >&2
    fi
    exit 1
  fi
else
  echo "==> 下一步: 上传该文件到服务器, 解压后执行 deploy/deploy.sh"
  echo "    （或加 --upload 参数由本脚本自动 FTP 上传，配置见 deploy/ftp.env.example）"
fi
