<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'name', 'description', 'image', 'category_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function directions()
    {
        return $this->hasMany(RecipeDirection::class);
    }

    public static function form()
    {
        return [
            'name' => '',
            'image' => '',
            'category_id' => '',
            'description' => '',
            'ingredients' => [
                RecipeIngredient::form()
            ],
            'directions' => [
                RecipeDirection::form(),
                RecipeDirection::form()
            ]
        ];
    }
}