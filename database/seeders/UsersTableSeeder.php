<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // 由于存在外键约束，先禁用外键检查
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // 清空表数据
        DB::table('recipe_user')->truncate();
        DB::table('recipes')->truncate();
        DB::table('users')->truncate();
        
        // 重新启用外键检查
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // 创建用户
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
        
        User::factory()->create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);
    }
}
