<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 记录 AI 生成菜谱时用户传入的主题/食材，
     * 用于相同请求直接复用数据库已有结果、避免重复消耗大模型。
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('ai_prompt')->nullable()->after('image')
                ->comment('AI 生成时用户传入的主题/食材，相同请求可复用');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('ai_prompt');
        });
    }
};
