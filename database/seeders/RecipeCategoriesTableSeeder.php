<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RecipeCategory;
use Illuminate\Support\Facades\DB;

class RecipeCategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清空表并重置自增ID
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        RecipeCategory::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 创建八大菜系主分类
        $cuisines = [
            ['name' => '川菜', 'description' => '以麻辣著称，口味浓郁'],
            ['name' => '粤菜', 'description' => '广东菜系，注重原汁原味'],
            ['name' => '苏菜', 'description' => '江苏菜系，讲究火候，口味清淡'],
            ['name' => '闽菜', 'description' => '福建菜系，以海鲜见长'],
            ['name' => '浙菜', 'description' => '浙江菜系，讲究鲜嫩软滑'],
            ['name' => '湘菜', 'description' => '湖南菜系，香辣可口'],
            ['name' => '徽菜', 'description' => '安徽菜系，重油重色'],
            ['name' => '鲁菜', 'description' => '山东菜系，讲究火候'],
        ];

        // 创建主分类并保存ID
        $mainCategories = [];
        foreach ($cuisines as $cuisine) {
          
           
            $category = RecipeCategory::create($cuisine);
           
            $mainCategories[$cuisine['name']] = $category->id;
        }

        // 定义八大菜系的子分类
        $subcategories = [
            // 川菜子分类
            '川菜' => [
                ['name' => '水煮系列', 'description' => '水煮鱼、水煮肉片等'],
                ['name' => '回锅肉系列', 'description' => '回锅肉、盐煎肉等'],
                ['name' => '火锅类', 'description' => '重庆火锅、串串香等'],
                ['name' => '泡菜系列', 'description' => '泡椒凤爪、泡菜鱼等'],
                ['name' => '麻婆豆腐', 'description' => '经典川菜'],
                ['name' => '宫保系列', 'description' => '宫保鸡丁、宫保虾球等'],
            ],
            // 粤菜子分类
            '粤菜' => [
                ['name' => '烧腊类', 'description' => '烧鹅、叉烧、烧肉等'],
                ['name' => '蒸菜类', 'description' => '清蒸鱼、蒸排骨等'],
                ['name' => '煲仔类', 'description' => '煲仔饭、啫啫煲等'],
                ['name' => '点心类', 'description' => '虾饺、烧卖、肠粉等'],
                ['name' => '老火靓汤', 'description' => '各种滋补汤品'],
            ],
            // 苏菜子分类
            '苏菜' => [
                ['name' => '淮扬菜', 'description' => '扬州炒饭、清炖蟹粉狮子头等'],
                ['name' => '苏锡菜', 'description' => '松鼠桂鱼、无锡排骨等'],
                ['name' => '南京菜', 'description' => '盐水鸭、鸭血粉丝汤等'],
                ['name' => '镇江菜', 'description' => '镇江肴肉、锅盖面等'],
            ],
            // 闽菜子分类
            '闽菜' => [
                ['name' => '福州菜', 'description' => '佛跳墙、荔枝肉等'],
                ['name' => '闽南菜', 'description' => '沙茶面、土笋冻等'],
                ['name' => '闽西菜', 'description' => '客家菜系，如梅菜扣肉等'],
                ['name' => '闽北菜', 'description' => '武夷山特色菜'],
            ],
            // 浙菜子分类
            '浙菜' => [
                ['name' => '杭州菜', 'description' => '西湖醋鱼、东坡肉等'],
                ['name' => '宁波菜', 'description' => '咸齑大黄鱼、宁波汤圆等'],
                ['name' => '绍兴菜', 'description' => '绍兴醉鸡、梅干菜扣肉等'],
                ['name' => '温州菜', 'description' => '温州鱼丸、瓯菜等'],
            ],
            // 湘菜子分类
            '湘菜' => [
                ['name' => '湘江流域菜', 'description' => '剁椒鱼头、毛氏红烧肉等'],
                ['name' => '湘西菜', 'description' => '腊味合蒸、血粑鸭等'],
                ['name' => '洞庭湖菜', 'description' => '洞庭湖鱼头、银鱼蒸蛋等'],
                ['name' => '湘南菜', 'description' => '东安鸡、永州血鸭等'],
            ],
            // 徽菜子分类
            '徽菜' => [
                ['name' => '皖南菜', 'description' => '黄山炖鸽、臭鳜鱼等'],
                ['name' => '沿江菜', 'description' => '无为板鸭、毛峰熏鲥鱼等'],
                ['name' => '沿淮菜', 'description' => '符离集烧鸡、八公山豆腐等'],
            ],
            // 鲁菜子分类
            '鲁菜' => [
                ['name' => '济南菜', 'description' => '九转大肠、糖醋鲤鱼等'],
                ['name' => '胶东菜', 'description' => '葱烧海参、油爆海螺等'],
                ['name' => '孔府菜', 'description' => '孔府一品锅、带子上朝等'],
                ['name' => '博山菜', 'description' => '博山酥锅、博山豆腐箱等'],
            ],
        ];

        // 创建子分类
        foreach ($subcategories as $cuisineName => $subs) {
            if (isset($mainCategories[$cuisineName])) {
                foreach ($subs as $sub) {
                    RecipeCategory::create([
                        'name' => $sub['name'],
                        'description' => $sub['description'],
                        'parent_id' => $mainCategories[$cuisineName]
                    ]);
                }
            }
        }
    }
}