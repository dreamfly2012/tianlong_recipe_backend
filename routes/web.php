<?php

use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

// 测试路由
Route::get('test', [TestController::class, 'index']);

// 加载其他路由文件
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/recipe.php';