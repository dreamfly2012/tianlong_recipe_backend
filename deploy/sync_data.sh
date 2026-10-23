#!/usr/bin/env bash
# ============================================================
#  只打包「数据」，用于给已有站点增量更新菜谱数据
#
#  背景：完整发布包（pack.sh）会带上全部代码 + 108MB 图片，
#        如果只是加了新菜谱，没必要重传整个项目。
#
#  用法:
#    bash deploy/sync_data.sh          # 默认打包最近 7 天新增的图片
#    bash deploy/sync_data.sh 30       # 最近 30 天
#    bash deploy/sync_data.sh all      # 全部图片（首次同步用）
#
#  产物: release/tianlong-data-YYYYmmdd-HHMMSS.tar.gz
#  服务器端: 解压后在项目根执行
#            php artisan db:seed --class="Database\Seeders\RecipesTableSeeder" --force
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OUT_DIR="release"
mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
PKG="$OUT_DIR/tianlong-data-${STAMP}.tar.gz"

MODE="${1:-7}"

if [ ! -f import/recipes_full.json ]; then
  echo "[x] 找不到 import/recipes_full.json"
  exit 1
fi

LIST="$(mktemp)"
trap 'rm -f "$LIST"' EXIT

if [ "$MODE" = "all" ]; then
  echo "==> 模式: 全部图片"
  find public/images -type f \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) > "$LIST"
elif [ "$MODE" = "diff" ]; then
  # 只打包服务器上缺失的图片（通过 FTP 列目录对比），最小上传量
  echo "==> 模式: 只打包服务器缺失的图片"
  if [ ! -f deploy/ftp.env ]; then
    echo "[x] 需要 deploy/ftp.env 才能对比服务器，回退为最近 7 天模式"
    find public/images -type f \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) \
      -newermt "-7 days" > "$LIST" 2>/dev/null || \
      find public/images -type f \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) > "$LIST"
  else
    TMPD="$(mktemp -d)"
    trap 'rm -rf "$TMPD"; rm -f "$LIST"' EXIT
    (
      set -a
      # shellcheck disable=SC1091
      . deploy/ftp.env
      set +a
      FTP_PORT="${FTP_PORT:-21}"
      curl -s --list-only --user "$FTP_USER:$FTP_PASS" \
        --connect-timeout 20 \
        "ftp://$FTP_HOST:$FTP_PORT/public/images/" 2>/dev/null \
        | grep -iE '\.(jpg|jpeg|png)$' | sed 's|.*/||' | sort -u > "$TMPD/srv.txt"
    )
    ls public/images | grep -iE '\.(jpg|jpeg|png)$' | sort -u > "$TMPD/loc.txt"
    SRV_N=$(wc -l < "$TMPD/srv.txt")
    LOC_N=$(wc -l < "$TMPD/loc.txt")
    comm -23 "$TMPD/loc.txt" "$TMPD/srv.txt" > "$TMPD/missing.txt"
    echo "    服务器已有: ${SRV_N}    本地: ${LOC_N}"
    while IFS= read -r f; do
      [ -n "$f" ] && echo "public/images/$f"
    done < "$TMPD/missing.txt" > "$LIST"
  fi
else
  if ! [[ "$MODE" =~ ^[0-9]+$ ]]; then
    echo "[x] 参数应为天数或 all，例如: bash deploy/sync_data.sh 7"
    exit 1
  fi
  echo "==> 模式: 最近 ${MODE} 天新增/修改的图片"
  find public/images -type f \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) \
    -newermt "-${MODE} days" > "$LIST" 2>/dev/null || {
      echo "[!] 当前 find 不支持 -newermt，改为全量打包"
      find public/images -type f \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) > "$LIST"
    }
fi

# 数据文件必带
echo "import/recipes_full.json" >> "$LIST"

IMG_N=$(grep -c '^public/images/' "$LIST" || true)
echo "    图片: ${IMG_N} 张"
COUNTS=$(php -r '$j=json_decode(file_get_contents("import/recipes_full.json"),true);echo is_array($j)?count($j):0;' 2>/dev/null || echo "?")
echo "    菜谱: ${COUNTS} 条"

if [ "$IMG_N" -eq 0 ]; then
  echo "    注意: 没有需要打包的图片，只打包数据文件"
fi

echo "==> 打包中"
tar -czf "$PKG" --files-from "$LIST"

SIZE=$(du -h "$PKG" | cut -f1)
echo ""
echo "==> 完成"
echo "    文件: $PKG"
echo "    大小: $SIZE"
echo ""
echo "下一步:"
echo "    bash deploy/upload.sh $PKG"
echo "    服务器上: tar -xzf $(basename "$PKG") && php artisan db:seed --class=\"Database\\\\Seeders\\\\RecipesIncrementalSeeder\" --force"
