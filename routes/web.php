<?php

use App\Http\Controllers\IndexController;
use Illuminate\Support\Facades\Route;

// 测试路由
Route::get('/', [IndexController::class, 'index']);


Route::get('/login',[IndexController::class, 'index'])->name('login');


// 添加回退路由，处理Vue Router的history模式
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');