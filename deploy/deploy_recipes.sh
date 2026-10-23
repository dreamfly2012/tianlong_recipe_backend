#!/bin/bash
# 批量导入 10,000 条食谱数据部署脚本
# 使用方法：bash deploy/deploy_recipes.sh

set -e

echo "=== 部署批量食谱数据 ==="
echo ""

# 检查 Docker
if ! docker compose ps > /dev/null 2>&1; then
    echo "启动 Docker Compose..."
    docker compose up -d
    sleep 10
fi

echo "[1/4] 清空缓存..."
docker compose exec -T php php artisan config:clear
docker compose exec -T php php artisan cache:clear

echo "[2/4] 截断并导入菜谱数据..."
docker compose exec -T php php artisan db:truncate --table=recipes,recipe_ingredients,recipe_directions 2>/dev/null || true
docker compose exec -T php php artisan db:seed --class=Database\\Seeders\\RecipesTableSeeder

echo "[3/4] 填充 meta 字段（幂等）..."
docker compose exec -T php php artisan db:seed --class=Database\\Seeders\\FillRecipeMetaFieldsSeeder

echo "[4/4] 验证数据..."
echo "菜谱总数："
docker compose exec -T php php artisan tinker --execute="echo DB::table('recipes')->count();";
echo "配料总数："
docker compose exec -T php php artisan tinker --execute="echo DB::table('recipe_ingredients')->count();";
echo "步骤总数："
docker compose exec -T php php artisan tinker --execute="echo DB::table('recipe_directions')->count();";

echo ""
echo "=== 部署完成 ==="
echo "数据文件：import/recipes_full.json"
echo " Seeder：database/seeders/RecipesTableSeeder.php"
