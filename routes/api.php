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
Route::get('recipe.favourites', [RecipeController::class, 'favourites']);
Route::get('recipe.daily', [RecipeController::class, 'daily']);
Route::get('recipe.ai', [RecipeController::class, 'ai']);

// 认证相关路由
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('register', [AuthController::class, 'register']);

Route::resource('recipes', RecipeController::class);