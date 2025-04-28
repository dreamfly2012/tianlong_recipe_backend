<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecipeController;

Route::any('recipe.lists', [RecipeController::class, 'lists']);
Route::any('recipe.info', [RecipeController::class, 'info']);
Route::any('recipe.category', [RecipeController::class, 'category']);
Route::any('recipe.search', [RecipeController::class, 'search']);

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('register', [AuthController::class, 'register']);

Route::resource('recipes', RecipeController::class);