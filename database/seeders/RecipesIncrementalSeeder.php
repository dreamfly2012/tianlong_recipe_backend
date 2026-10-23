<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * 增量导入菜谱数据
 *
 * 与 RecipesTableSeeder 的区别：
 *   - RecipesTableSeeder：TRUNCATE 三张表后全量重建（会清掉线上原有数据）
 *   - 本 Seeder：保留已有数据，只插入数据库里没有的菜谱
 *
 * 去重规则（依次尝试，命中即视为重复）：
 *   1. 菜名（trim + 转小写后比较）
 *   2. 图片文件名（image 字段非空时）
 *
 * 用法：
 *   php artisan db:seed --class="Database\\Seeders\\RecipesIncrementalSeeder"
 *   php artisan db:seed --class="Database\\Seeders\\RecipesIncrementalSeeder" --force
 */
class RecipesIncrementalSeeder extends Seeder
{
    /** @var array<string,bool> 已存在的菜名指纹 */
    private array $existingNames = [];

    /** @var array<string,bool> 已存在的图片指纹 */
    private array $existingImages = [];

    private int $inserted = 0;
    private int $skippedName = 0;
    private int $skippedImage = 0;
    private int $invalid = 0;

    public function run(): void
    {
        $path = base_path('import/recipes_full.json');
        if (! File::exists($path)) {
            $this->command->warn("未找到 {$path}，跳过增量导入。");
            return;
        }

        $recipes = json_decode(File::get($path), true);
        if (! is_array($recipes) || $recipes === []) {
            $this->command->warn('recipes_full.json 内容为空或格式错误，跳过。');
            return;
        }

        $this->loadExisting();

        $total = count($recipes);
        $before = DB::table('recipes')->count();

        $this->command->info("待处理 {$total} 条，数据库现有 {$before} 条");

        DB::transaction(function () use ($recipes) {
            foreach ($recipes as $recipe) {
                $this->importOne($recipe);
            }
        });

        $after = DB::table('recipes')->count();

        $this->command->info('');
        $this->command->info('========== 增量导入结果 ==========');
        $this->command->info("新增:        {$this->inserted} 条");
        $this->command->info("跳过(重名):  {$this->skippedName} 条");
        $this->command->info("跳过(同图):  {$this->skippedImage} 条");
        $this->command->info("无效数据:    {$this->invalid} 条");
        $this->command->info("数据库:      {$before} -> {$after} 条");
        $this->command->info('=================================');
    }

    /**
     * 把数据库里已有的菜名/图片指纹载入内存，避免逐条查库
     */
    private function loadExisting(): void
    {
        DB::table('recipes')
            ->select('name', 'image')
            ->orderBy('id')
            ->chunk(1000, function ($rows) {
                foreach ($rows as $row) {
                    $key = $this->nameKey($row->name ?? '');
                    if ($key !== '') {
                        $this->existingNames[$key] = true;
                    }
                    $img = trim((string) ($row->image ?? ''));
                    if ($img !== '') {
                        $this->existingImages[$img] = true;
                    }
                }
            });
    }

    private function nameKey(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        // 全角空格、连续空白归一，再转小写
        $name = preg_replace('/\s+/u', ' ', $name);
        return mb_strtolower($name, 'UTF-8');
    }

    private function importOne(array $recipe): void
    {
        $name = trim((string) ($recipe['name'] ?? ''));
        if ($name === '') {
            $this->invalid++;
            return;
        }

        $key = $this->nameKey($name);
        if (isset($this->existingNames[$key])) {
            $this->skippedName++;
            return;
        }

        $image = trim((string) ($recipe['image'] ?? ''));
        if ($image !== '' && isset($this->existingImages[$image])) {
            $this->skippedImage++;
            return;
        }

        $categoryId = (int) ($recipe['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $categoryId = 1;
        }

        $recipeId = DB::table('recipes')->insertGetId([
            'user_id'      => (int) ($recipe['user_id'] ?? 1) ?: 1,
            'category_id'  => $categoryId,
            'name'         => $name,
            'description'  => (string) ($recipe['description'] ?? ''),
            'image'        => $image,
            'cooking_time' => $recipe['cooking_time'] ?? null,
            'difficulty'   => $recipe['difficulty'] ?? null,
            'tags'         => $recipe['tags'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $ings = [];
        foreach (($recipe['ingredients'] ?? []) as $ing) {
            $nm = trim((string) ($ing['name'] ?? ''));
            if ($nm === '') {
                continue;
            }
            $ings[] = [
                'recipe_id' => $recipeId,
                'name'      => $nm,
                'qty'       => (string) ($ing['qty'] ?? '适量'),
            ];
        }
        if ($ings !== []) {
            DB::table('recipe_ingredients')->insert($ings);
        }

        $dirs = [];
        foreach (($recipe['directions'] ?? []) as $dir) {
            $desc = trim((string) ($dir['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $dirs[] = [
                'recipe_id'   => $recipeId,
                'description' => $desc,
            ];
        }
        if ($dirs !== []) {
            DB::table('recipe_directions')->insert($dirs);
        }

        // 立刻记入去重表，防止同一批 JSON 内出现重复
        $this->existingNames[$key] = true;
        if ($image !== '') {
            $this->existingImages[$image] = true;
        }

        $this->inserted++;

        if ($this->inserted % 100 === 0) {
            $this->command->info("  已新增 {$this->inserted} 条…");
        }
    }
}
