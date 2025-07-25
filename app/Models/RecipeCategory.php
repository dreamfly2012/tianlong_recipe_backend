<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeCategory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recipe_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'parent_id',
    ];

 

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(RecipeCategory::class, 'parent_id');
    }

    /**
     * Check if the category has children.
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }
}