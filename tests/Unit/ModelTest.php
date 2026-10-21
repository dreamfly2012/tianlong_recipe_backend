<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeIngredient;
use App\Models\RecipeDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_recipes(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->recipes->contains($recipe));
    }

    public function test_user_can_favorite_recipes(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create();

        $user->favoriteRecipes()->attach($recipe->id);

        $this->assertTrue($user->favoriteRecipes->contains($recipe));
    }

    public function test_recipe_has_ingredients(): void
    {
        $recipe = Recipe::factory()->create();
        $ingredient = RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'name' => '鸡蛋',
            'qty' => '3个',
        ]);

        $this->assertTrue($recipe->ingredients->contains($ingredient));
    }

    public function test_recipe_has_directions(): void
    {
        $recipe = Recipe::factory()->create();
        $direction = RecipeDirection::create([
            'recipe_id' => $recipe->id,
            'description' => '第一步：准备食材',
        ]);

        $this->assertTrue($recipe->directions->contains($direction));
    }

    public function test_recipe_has_category(): void
    {
        $category = RecipeCategory::factory()->create();
        $recipe = Recipe::factory()->create(['category_id' => $category->id]);

        $this->assertEquals($category->id, $recipe->category_id);
    }

    public function test_recipe_form_returns_default_data(): void
    {
        $form = Recipe::form();

        $this->assertArrayHasKey('name', $form);
        $this->assertArrayHasKey('description', $form);
        $this->assertArrayHasKey('ingredients', $form);
        $this->assertArrayHasKey('directions', $form);
        $this->assertEquals('', $form['name']);
    }

    public function test_ingredient_form_returns_default_data(): void
    {
        $form = RecipeIngredient::form();

        $this->assertArrayHasKey('name', $form);
        $this->assertArrayHasKey('qty', $form);
        $this->assertEquals('', $form['name']);
    }

    public function test_direction_form_returns_default_data(): void
    {
        $form = RecipeDirection::form();

        $this->assertArrayHasKey('description', $form);
        $this->assertEquals('', $form['description']);
    }

    public function test_category_has_children(): void
    {
        $parent = RecipeCategory::factory()->create(['parent_id' => 0]);
        $child = RecipeCategory::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($parent->hasChildren());
    }

    public function test_category_form_returns_default_data(): void
    {
        $form = RecipeCategory::form();

        $this->assertArrayHasKey('name', $form);
        $this->assertArrayHasKey('parent_id', $form);
        $this->assertEquals(0, $form['parent_id']);
    }
}
