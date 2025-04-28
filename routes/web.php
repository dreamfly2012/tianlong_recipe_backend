<?php

use App\Http\Controllers\TestController;

Route::get('test', [TestController::class, 'index']);

Route::get('/{any}', function () {
    return view('welcome');
})->where(['any' => '.*']);