<?php

namespace Tests\Unit;

use App\Models\Recipe;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    public function test_recipe_form_structure(): void
    {
        $form = Recipe::form();

        // 验证表单结构
        $this->assertEquals('', $form['name']);
        $this->assertEquals('', $form['image']);
        $this->assertEquals('', $form['category_id']);
        $this->assertEquals('', $form['description']);
        $this->assertCount(1, $form['ingredients']);
        $this->assertCount(2, $form['directions']);

        // 验证嵌套结构
        $this->assertEquals('', $form['ingredients'][0]['name']);
        $this->assertEquals('', $form['ingredients'][0]['qty']);
        $this->assertEquals('', $form['directions'][0]['description']);
    }

    public function test_recipe_fillable_fields(): void
    {
        $recipe = new Recipe();
        $this->assertContains('name', $recipe->getFillable());
        $this->assertContains('description', $recipe->getFillable());
        $this->assertContains('image', $recipe->getFillable());
        $this->assertContains('category_id', $recipe->getFillable());
    }

    public function test_recipe_relationships_exist(): void
    {
        $recipe = Recipe::factory()->create();
        $this->assertTrue(method_exists($recipe, 'user'));
        $this->assertTrue(method_exists($recipe, 'ingredients'));
        $this->assertTrue(method_exists($recipe, 'directions'));
        $this->assertTrue(method_exists($recipe, 'favoritedBy'));
    }
}
