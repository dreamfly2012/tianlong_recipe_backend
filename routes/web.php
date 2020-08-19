<?php

Route::get('test','TestController@index');

Route::get('/{any}', function () {
    return view('welcome');
})->where(['any' => '.*']);
