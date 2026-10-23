<?php

namespace App\Models;

use App\Services\CosService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'image', 'category_id', 'user_id', 'ai_prompt',
        'cooking_time', 'difficulty', 'tags',
    ];

    /**
     * Expose the full image URL alongside the raw file name.
     *
     * @var array<int, string>
     */
    protected $appends = ['image_src'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(RecipeCategory::class);
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function directions()
    {
        return $this->hasMany(RecipeDirection::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'recipe_user', 'recipe_id', 'user_id')->withTimestamps();
    }

    /**
     * Image URL: prefer the local public/images file when it exists
     * (covers seeded / locally stored images), otherwise fall back to
     * the CDN-prefixed COS URL when COS is configured.
     */
    public function getImageSrcAttribute(): ?string
    {
        $image = $this->attributes['image'] ?? null;

        if (empty($image)) {
            return null;
        }

        $image = ltrim($image, '/');

        // 本地 public/images 已存在同名文件时优先走本地，避免 COS 上缺失该文件导致 404。
        if (file_exists(public_path('images/'.$image))) {
            return '/images/'.$image;
        }

        // 其余交给自封装的 CosService：配置了 CDN 返回 CDN URL，否则返回本地 /images URL。
        return app(CosService::class)->url($image);
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
