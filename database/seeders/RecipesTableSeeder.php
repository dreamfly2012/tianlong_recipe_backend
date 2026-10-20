<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 由于存在外键约束，先禁用外键检查
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // 清空表数据
        DB::table('recipe_ingredients')->truncate();
        DB::table('recipe_directions')->truncate();
        DB::table('recipes')->truncate();
        
        // 重新启用外键检查
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // 创建示例菜谱
        $recipes = [
            [
                'user_id' => 1,
                'category_id' => 1,
                'name' => '番茄炒蛋',
                'description' => '一道经典的中式家常菜，简单易做，营养丰富。',
                'image' => 'tomato_egg.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'category_id' => 2,
                'name' => '宫保鸡丁',
                'description' => '川菜代表之一，口感麻辣鲜香，鸡肉嫩滑。',
                'image' => 'kung_pao_chicken.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'category_id' => 3,
                'name' => '麻婆豆腐',
                'description' => '四川传统名菜，豆腐软嫩，麻辣鲜香。',
                'image' => 'mapo_tofu.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('recipes')->insert($recipes);
        
        // 为菜谱添加配料
        $ingredients = [
            [
                'recipe_id' => 1,
                'name' => '鸡蛋',
                'qty' => '3个',
            ],
            [
                'recipe_id' => 1,
                'name' => '番茄',
                'qty' => '2个',
            ],
            [
                'recipe_id' => 1,
                'name' => '食用油',
                'qty' => '适量',
            ],
        ];
        
        DB::table('recipe_ingredients')->insert($ingredients);
        
        // 为菜谱添加制作步骤
        $directions = [
            [
                'recipe_id' => 1,
                'description' => '鸡蛋打散，番茄切块备用。',
            ],
            [
                'recipe_id' => 1,
                'description' => '锅中放油，倒入鸡蛋液炒熟盛出。',
            ],
            [
                'recipe_id' => 1,
                'description' => '锅中再放少许油，放入番茄块炒软。',
            ],
            [
                'recipe_id' => 1,
                'description' => '将炒好的鸡蛋倒回锅中，与番茄一起翻炒均匀，调味即可。',
            ],
        ];
        
        DB::table('recipe_directions')->insert($directions);
    }
}