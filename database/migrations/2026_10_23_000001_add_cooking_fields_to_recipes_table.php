<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->integer('cooking_time')->nullable()->after('image')
                ->comment('烹饪时长（分钟）');
            $table->string('difficulty')->nullable()->after('cooking_time')
                ->comment('难度：简单/中等/困难');
            $table->string('tags')->nullable()->after('difficulty')
                ->comment('标签，逗号分隔');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['cooking_time', 'difficulty', 'tags']);
        });
    }
};
