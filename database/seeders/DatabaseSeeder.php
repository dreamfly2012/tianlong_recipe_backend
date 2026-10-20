<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 为了处理外键约束，我们需要按特定顺序截断表
        $this->call([
            UsersTableSeeder::class,
            RecipeCategoriesTableSeeder::class,
            RecipesTableSeeder::class,
        ]);
    }
}