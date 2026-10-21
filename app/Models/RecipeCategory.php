<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeCategory extends Model
{
    use HasFactory;

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
        'description',
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

    /**
     * 返回表单默认数据
     */
    public static function form()
    {
        return [
            'name' => '',
            'parent_id' => 0,
        ];
    }
}