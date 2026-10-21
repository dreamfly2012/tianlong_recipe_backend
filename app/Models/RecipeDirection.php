<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeDirection extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id', 'description'
    ];

    public $timestamps = false;
    

    public static function form()
    {
        return [
            'description' => ''
        ];
    }
}