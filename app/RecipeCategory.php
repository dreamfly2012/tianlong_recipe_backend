<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeCategory extends Model
{
    public $timestamps = false;

    protected $table = 'recipe_category';

    public function recipes()
    {
       return $this->hasMany(Recipe::class);
    }
}
