<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'image'       => 'recipes/'.$this->faker->uuid().'.jpg',
            'category_id' => RecipeCategoryFactory::new(),
            'user_id'     => User::factory(),
            'cooking_time' => $this->faker->numberBetween(10, 120),
            'difficulty'   => $this->faker->randomElement(['简单', '中等', '困难']),
            'tags'         => $this->faker->randomElement([null, '家常,快手,下饭']),
        ];
    }
}
