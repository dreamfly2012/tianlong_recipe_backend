<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeCategory extends Model
{
    protected $fillable = [
    	'name'
    ];

    public $timestamps = false;

    protected $table = 'recipe_category';

    public static function form()
    {
    	return [
    		'name' => ''
    	];
    }
}
