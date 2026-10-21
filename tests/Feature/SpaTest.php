<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_can_be_rendered(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_recipe_list_endpoint_is_public(): void
    {
        $this->getJson('/api/recipe.lists')
            ->assertStatus(200)
            ->assertJsonPath('code', 0);
    }
}
