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
        // 由于存在外键约束，先禁用外键检查（MySQL）
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('recipe_ingredients')->truncate();
        DB::table('recipe_directions')->truncate();
        DB::table('recipes')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $path = base_path('import/recipes_full.json');
        if (! File::exists($path)) {
            $this->command->warn("未找到 {$path}，跳过菜谱填充。");
            return;
        }

        $recipes = json_decode(File::get($path), true);

        foreach ($recipes as $recipe) {
            $recipeId = DB::table('recipes')->insertGetId([
                'user_id'     => $recipe['user_id'] ?? 1,
                'category_id' => $recipe['category_id'],
                'name'        => $recipe['name'],
                'description' => $recipe['description'],
                'image'       => $recipe['image'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            foreach ($recipe['ingredients'] as $ing) {
                DB::table('recipe_ingredients')->insert([
                    'recipe_id' => $recipeId,
                    'name'      => $ing['name'],
                    'qty'       => $ing['qty'],
                ]);
            }

            foreach ($recipe['directions'] as $dir) {
                DB::table('recipe_directions')->insert([
                    'recipe_id'   => $recipeId,
                    'description' => $dir['description'],
                ]);
            }
        }

        $this->command->info('已导入 '.count($recipes).' 条菜谱（含配料与步骤）。');
    }
}
