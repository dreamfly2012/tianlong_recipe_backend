<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RecipesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 禁用外键检查
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 清空关联表
        DB::table('recipe_ingredients')->truncate();
        DB::table('recipe_directions')->truncate();
        DB::table('recipes')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $path = base_path('import/recipes_full.json');
        if (!File::exists($path)) {
            $this->command->warn("未找到 {$path}，跳过菜谱填充。");
            return;
        }

        $recipes = json_decode(File::get($path), true);
        $total = count($recipes);
        $this->command->info("开始导入 {$total} 条菜谱...");

        $batchSize = 500;
        $processed = 0;
        $startTime = time();

        foreach (array_chunk($recipes, $batchSize) as $chunk) {
            foreach ($chunk as $recipe) {
                // 插入菜谱
                $recipeId = DB::table('recipes')->insertGetId([
                    'user_id'     => $recipe['user_id'] ?? 1,
                    'category_id' => $recipe['category_id'],
                    'name'        => $recipe['name'],
                    'description' => $recipe['description'] ?? '',
                    'image'       => $recipe['image'] ?? '',
                    'cooking_time' => $recipe['cooking_time'] ?? null,
                    'difficulty'  => $recipe['difficulty'] ?? null,
                    'tags'        => $recipe['tags'] ?? null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // 插入配料
                foreach (($recipe['ingredients'] ?? []) as $ing) {
                    DB::table('recipe_ingredients')->insert([
                        'recipe_id' => $recipeId,
                        'name'      => $ing['name'] ?? '',
                        'qty'       => $ing['qty'] ?? '',
                    ]);
                }

                // 插入步骤
                foreach (($recipe['directions'] ?? []) as $dir) {
                    DB::table('recipe_directions')->insert([
                        'recipe_id'   => $recipeId,
                        'description' => $dir['description'] ?? '',
                    ]);
                }

                $processed++;
            }

            // 进度报告
            $elapsed = max(1, time() - $startTime);
            $rate = $processed / $elapsed;
            $eta = $rate > 0 ? (int) floor(($total - $processed) / $rate) : 0;
            $this->command->info(sprintf(
                '进度：%d/%d (%.1f 条/秒, 预计剩余 %d 秒)',
                $processed,
                $total,
                $rate,
                $eta
            ));
        }

        $this->command->info("导入完成！共 {$processed} 条菜谱。");
    }
}
