#!/usr/bin/env bash
# ============================================================
#  上传发布包到服务器（FTP / FTPS）
#
#  用法:
#    bash deploy/upload.sh release/tianlong-recipe-20261023-205200.tar.gz
#    bash deploy/upload.sh release/tianlong-data-20261023-205200.tar.gz
#
#  配置: deploy/ftp.env（FTP_HOST / FTP_PORT / FTP_USER / FTP_PASS / FTP_DIR / FTP_SSL）
#  说明: ftp.env 已被 .gitignore 忽略，不会进版本库
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="deploy/ftp.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "[x] 缺少 $ENV_FILE，请先按模板创建并填写服务器信息"
  exit 1
fi

set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "用法: bash deploy/upload.sh <本地文件路径>"
  echo "示例: bash deploy/upload.sh release/tianlong-recipe-20261023-205200.tar.gz"
  exit 1
fi

: "${FTP_HOST:?FTP_HOST 未配置}"
: "${FTP_USER:?FTP_USER 未配置}"
: "${FTP_PASS:?FTP_PASS 未配置}"
FTP_PORT="${FTP_PORT:-21}"
FTP_DIR="${FTP_DIR:-/}"
FTP_SSL="${FTP_SSL:-0}"

# 目录末尾补 /
case "$FTP_DIR" in
  */) ;;
  *) FTP_DIR="$FTP_DIR/" ;;
esac

PROTO="ftp"
if [ "$FTP_SSL" = "1" ]; then
  PROTO="ftps"
fi

SIZE=$(du -h "$FILE" | cut -f1)
NAME=$(basename "$FILE")
URL="$PROTO://$FTP_HOST:$FTP_PORT$FTP_DIR$NAME"

echo "==> 上传"
echo "    文件: $FILE ($SIZE)"
echo "    目标: $URL"
echo ""

# --retry 处理网络抖动；--ftp-create-dirs 自动建目录
if curl --fail --ftp-create-dirs \
        --user "$FTP_USER:$FTP_PASS" \
        --retry 3 --retry-delay 5 \
        --connect-timeout 30 \
        -T "$FILE" "$URL" \
        --progress-bar; then
  echo ""
  echo "[OK] 上传完成: $NAME"
  echo ""
  echo "下一步（登录服务器）:"
  echo "    cd <站点目录>"
  echo "    tar -xzf $NAME"
  echo "    bash deploy/deploy.sh        # 或 bash deploy/deploy.sh 按提示选择是否导入数据"
else
  echo ""
  echo "[x] 上传失败"
  echo ""
  echo "    如果是 553（Failed FTP upload: 553）："
  echo "      服务器拒绝在根目录创建文件，说明 FTP 账号对站点根没有写权限。"
  echo "      实测该服务器：/ 不可写，但 /public/ /storage/ /import/ 都可写。"
  echo ""
  echo "      → 改用逐文件直传（不需要根目录写权限，也不需要 SSH）："
  echo "          bash deploy/ftp_put_files.sh"
  echo ""
  echo "    其他排查："
  echo "      1) FTP 账号密码是否正确"
  echo "      2) 端口 21 是否放行"
  echo "      3) FTP_DIR 是否有写权限（改成 /public/ 试试）"
  echo "      4) FTP_SSL 是否需要设为 1"
  exit 1
fi
