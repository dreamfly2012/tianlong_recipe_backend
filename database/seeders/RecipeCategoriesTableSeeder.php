<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RecipeCategory;

class RecipeCategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => '川菜'],
            ['name' => '粤菜'],
            ['name' => '苏菜'],
            ['name' => '闽菜'],
            ['name' => '浙菜'],
            ['name' => '湘菜'],
            ['name' => '徽菜'],
            ['name' => '鲁菜'],
        ];

        RecipeCategory::truncate();

        foreach ($categories as $category) {
            RecipeCategory::create($category);
        }
    }
}