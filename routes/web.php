<?php

use App\Http\Controllers\IndexController;
use Illuminate\Support\Facades\Route;

// 测试路由
Route::get('/', [IndexController::class, 'index']);


Route::get('/login',[IndexController::class, 'index'])->name('login');


// 添加回退路由，处理Vue Router的history模式
// 排除 api/ 前缀：否则拼错的接口路径会被这条只支持 GET 的路由接住，
// POST 请求返回 405（方法不允许）而不是真实的 404，极难排查。
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '(?!api/).*');