<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 为已有菜谱填充 cooking_time / difficulty / tags 字段。
 *
 * 逻辑：
 *  - 已填写的字段跳过（幂等）
 *  - cooking_time：随机 10~90 分钟
 *  - difficulty：简单 / 中等 / 困难 三档等概率
 *  - tags：从固定词库中随机抽取 1~3 个，逗号分隔
 */
class FillRecipeMetaFieldsSeeder extends Seeder
{
    private const DIFFICULTIES = ['简单', '中等', '困难'];

    private const TAG_POOLS = [
        '家常', '快手', '下饭', '清淡', '麻辣', '酸甜',
        '低脂', '高蛋白', '减脂', '开胃', '暖心', '节日',
        '早餐', '夜宵', '宝宝辅食', '素食', '低碳', '健身',
    ];

    public function run(): void
    {
        $affected = 0;

        // 取所有尚未填过这三个字段中任一记录的 id，避免无意义更新
        $records = DB::table('recipes')
            ->whereNull('cooking_time')
            ->orWhereNull('difficulty')
            ->orWhereNull('tags')
            ->get(['id']);

        foreach ($records as $record) {
            $cookingTime = random_int(10, 90);
            $difficulty = self::DIFFICULTIES[array_rand(self::DIFFICULTIES)];
            $tagCount = random_int(1, 3);

            $shuffled = self::TAG_POOLS;
            shuffle($shuffled);
            $tags = implode(',', array_slice($shuffled, 0, $tagCount));

            DB::table('recipes')
                ->where('id', $record->id)
                ->update([
                    'cooking_time' => $cookingTime,
                    'difficulty'   => $difficulty,
                    'tags'         => $tags,
                    'updated_at'   => now(),
                ]);
            $affected++;
        }

        $this->command->info("已为 {$affected} 条菜谱填充 meta 字段。");
    }
}
