# 批量导入食谱数据 - 改动清单

## 新增文件

### 数据生成器
- `import/generate_recipes_local.py` - 本地菜谱数据生成器（不依赖外部 API）
- `import/generate_recipes.py` - 尝试从 TheMealDB 获取数据的生成器（备用）
- `import/fetch_xiachufang_recipes.py` - 下厨房爬虫（因反爬限制，未使用）

### 部署脚本
- `deploy/deploy_recipes.sh` - 一键部署脚本

## 修改文件

### Seeder
- `database/seeders/RecipesTableSeeder.php`
  - 新增 `cooking_time`, `difficulty`, `tags` 字段支持
  - 优化性能：分批插入（500 条/批）+ 进度报告

## 数据文件

- `import/recipes_full.json` - 10,000 条菜谱数据（4.7MB）
  - 包含 84 条原始真实数据 + 9,916 条生成数据
  - 覆盖 8 大菜系（category_id 1-8）

## 数据库变更

无需新增 migration，现有字段已包含：
- `recipes.cooking_time` (nullable integer)
- `recipes.difficulty` (nullable string)
- `recipes.tags` (nullable string)

## 导入命令

```bash
# 方式 1：使用 Seeder（推荐）
docker compose exec php php artisan db:seed --class=Database\\Seeders\\RecipesTableSeeder

# 方式 2：完整部署
bash deploy/deploy_recipes.sh

# 方式 3：重新生成数据
python3 import/generate_recipes_local.py --target 10000
```

## 数据量统计

| 项目 | 数量 |
|------|------|
| 菜谱总数 | 10,000 |
| 平均配料数 | ~8 个 |
| 平均步骤数 | ~5 个 |
| 图片目录 | public/images/（暂无真实图片）|

## 注意事项

1. **图片问题**：当前生成的数据没有真实图片（image_url 为空）
   - 解决方案：后续可用 AI 生成图片或从公开源下载
   
2. **数据质量**：生成的数据是模板化的，缺乏真实菜谱的详细信息
   - 建议：用 AI 重新生成更真实的描述和步骤

3. **性能**：导入 10,000 条数据约需 2-5 分钟
   - 已优化分批插入，避免超时
