<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;


class TestController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:api')->only('logout');
    }

    //
    public function index(Request $request)
    {
        //$ref = new \ReflectionClass(TestController::class);
        //dump($ref->getMethods());
        //$a = [3,3,5];
        //echo __CLASS__;
        //return "hello world";
        //var_dump(get_class($this));
        //dd(phpinfo());
        $cacheRecipes = Redis::get('recipes');
        if($cacheRecipes) {
            dump('使用缓存输出');
            dd($cacheRecipes);
        } else {
            dump('直连数据库输出');
            $recipes = \App\Recipe::all();
            Redis::set('recipes', $recipes);
            dd($recipes);
        }
        Redis::set('name', 'menghuiguli');
        $values = Redis::get('name');
        dd($values);
        $user = Redis::get('user:profile:');
        dump($user);
        //dump($request);


    }
}
