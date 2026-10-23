#!/usr/bin/env bash
# ============================================================
#  纯 FTP 直传：不打包、不需要 SSH，逐个文件传到服务器
#
#  适合只有 FTP 权限（没有 SSH）的场景。
#  默认只传「服务器上缺失的图片」+ recipes_full.json。
#
#  用法:
#    bash deploy/ftp_put_files.sh            # 只传缺失的图片 + 数据文件
#    bash deploy/ftp_put_files.sh --all      # 传全部图片（首次用）
#
#  传完后仍需在服务器执行（宝塔面板 -> 终端）：
#    php artisan db:seed --class="Database\Seeders\RecipesTableSeeder" --force
#    php artisan cache:clear
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -f deploy/ftp.env ]; then
  echo "[x] 缺少 deploy/ftp.env"
  exit 1
fi

set -a
# shellcheck disable=SC1091
. deploy/ftp.env
set +a

: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASS:?}"
FTP_PORT="${FTP_PORT:-21}"
FTP_DIR="${FTP_DIR:-/}"
FTP_SSL="${FTP_SSL:-0}"
case "$FTP_DIR" in */) ;; *) FTP_DIR="$FTP_DIR/" ;; esac

PROTO="ftp"
[ "$FTP_SSL" = "1" ] && PROTO="ftps"

MODE="${1:-missing}"

TMPD="$(mktemp -d)"
trap 'rm -rf "$TMPD"' EXIT

if [ "$MODE" = "--all" ]; then
  echo "==> 模式: 上传全部图片"
  ls public/images | grep -iE '\.(jpg|jpeg|png)$' | sort > "$TMPD/send_imgs.txt"
else
  echo "==> 模式: 只上传服务器缺失的图片"
  curl -s --list-only --user "$FTP_USER:$FTP_PASS" --connect-timeout 20 \
    "$PROTO://$FTP_HOST:$FTP_PORT${FTP_DIR}public/images/" 2>/dev/null \
    | grep -iE '\.(jpg|jpeg|png)$' | sed 's|.*/||' | sort -u > "$TMPD/srv.txt"
  ls public/images | grep -iE '\.(jpg|jpeg|png)$' | sort -u > "$TMPD/loc.txt"
  comm -23 "$TMPD/loc.txt" "$TMPD/srv.txt" > "$TMPD/send_imgs.txt"
  echo "    服务器已有: $(wc -l < "$TMPD/srv.txt")    本地: $(wc -l < "$TMPD/loc.txt")"
fi

IMG_N=$(wc -l < "$TMPD/send_imgs.txt")
echo "    待传图片: ${IMG_N} 张"
echo ""

FAIL=0
i=0

if [ "$IMG_N" -gt 0 ]; then
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    i=$((i + 1))
    if curl -s --fail --ftp-create-dirs --user "$FTP_USER:$FTP_PASS" \
         --connect-timeout 30 --retry 2 --retry-delay 3 \
         -T "public/images/$f" \
         "$PROTO://$FTP_HOST:$FTP_PORT${FTP_DIR}public/images/$f"; then
      [ $((i % 20)) -eq 0 ] && echo "  已传 $i/$IMG_N"
    else
      FAIL=$((FAIL + 1))
      echo "  [失败] $f"
    fi
  done < "$TMPD/send_imgs.txt"
  echo "  图片传输完成: 成功 $((IMG_N - FAIL)) / $IMG_N"
fi

echo ""
echo "==> 上传数据文件 import/recipes_full.json"
if curl -s --fail --ftp-create-dirs --user "$FTP_USER:$FTP_PASS" \
     --connect-timeout 30 --retry 2 --retry-delay 3 \
     -T "import/recipes_full.json" \
     "$PROTO://$FTP_HOST:$FTP_PORT${FTP_DIR}import/recipes_full.json"; then
  echo "  [OK] recipes_full.json"
else
  echo "  [失败] recipes_full.json"
  FAIL=$((FAIL + 1))
fi

echo ""
if [ "$FAIL" -eq 0 ]; then
  echo "=========================================="
  echo "  全部传输成功"
  echo "=========================================="
else
  echo "[!] 有 ${FAIL} 个文件失败，可重新运行本脚本重试（幂等）"
fi

echo ""
echo "下一步（服务器终端 / 宝塔终端）:"
echo "    cd ${FTP_DIR}"
echo "    php artisan db:seed --class=\"Database\\\\Seeders\\\\RecipesIncrementalSeeder\" --force"
echo "    php artisan cache:clear"
