<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wechat_openid')->nullable()->unique()->after('email');
            $table->string('wechat_unionid')->nullable()->unique()->after('wechat_openid');
            $table->string('wechat_avatar')->nullable()->after('wechat_unionid');
            $table->string('wechat_nickname')->nullable()->after('wechat_avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wechat_openid', 'wechat_unionid', 'wechat_avatar', 'wechat_nickname']);
        });
    }
};