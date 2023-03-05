<?php

Route::any('recipe.lists','RecipeController@lists');
Route::any('recipe.info','RecipeController@info');
Route::any('recipe.category','RecipeController@category');
Route::any('recipe.search', 'RecipeController@search');

Route::post('login', 'AuthController@login');
Route::post('logout', 'AuthController@logout');
Route::post('register', 'AuthController@register');

Route::resource('recipes', 'RecipeController');
