<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id', 'name', 'qty'
    ];

    public $timestamps = false;

    public static function form()
    {
        return [
            'name' => '',
            'qty' => ''
        ];
    }
}