<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

// 支持 recipe.lists 格式的路由
Route::get('recipe.lists', [RecipeController::class, 'lists']);
Route::get('recipe.info', [RecipeController::class, 'info']);
Route::get('recipe.category', [RecipeController::class, 'category']);
Route::get('recipe.search', [RecipeController::class, 'search']);
Route::get('recipe.create', [RecipeController::class, 'create']);
Route::get('recipe.favourites', [RecipeController::class, 'favoriteList']);
Route::get('recipe.favourite', [RecipeController::class, 'favorite']);
Route::get('recipe.unfavourite', [RecipeController::class, 'unfavorite']);
Route::get('recipe.isfavourite', [RecipeController::class, 'isFavorite']);


// 个性化 AI 推荐
Route::get('recipe.daily', [RecipeController::class, 'daily']);
Route::get('recipe.ai', [RecipeController::class, 'ai']);
Route::get('recipe.personalized', [RecipeController::class, 'personalized']);
Route::post('recipe.generate', [RecipeController::class, 'generate']);

// 认证相关路由
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('register', [AuthController::class, 'register']);

// 微信小程序登录路由
Route::post('wechat/login', [AuthController::class, 'wechatLogin']);

Route::resource('recipes', RecipeController::class);