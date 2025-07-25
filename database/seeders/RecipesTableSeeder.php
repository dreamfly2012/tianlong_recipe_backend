<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory;
use App\Models\Recipe;
use App\Models\RecipeDirection;
use App\Models\RecipeIngredient;

class RecipesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Factory::create();

        Recipe::truncate();
        RecipeIngredient::truncate();
        RecipeDirection::truncate();

        foreach(range(1, 10) as $i) {
        	$recipe = Recipe::create([
        		'user_id' => $i,
				'category_id' => mt_rand(1, 8),
        		'name' => $faker->sentence,
        		'description' => $faker->paragraph(mt_rand(10, 20)),
        		'image' => 'test.png'
        	]);

        	foreach(range(1, mt_rand(3, 12)) as $j) {
        		RecipeIngredient::create([
        			'recipe_id' => $recipe->id,
        			'name' => $faker->word,
        			'qty' => mt_rand(1, 12).' Kg'
        		]);
        	}

        	foreach(range(1, mt_rand(5, 12)) as $k) {
        		RecipeDirection::create([
        			'recipe_id' => $recipe->id,
        			'description' => $faker->sentence,
        		]);
        	}
        }
    }
}
