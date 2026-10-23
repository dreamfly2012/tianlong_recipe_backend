<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_history_routes_still_fall_back_to_the_app_shell(): void
    {
        $this->get('/recipe/create')->assertStatus(200);
    }

    public function test_unknown_api_path_returns_404_instead_of_405(): void
    {
        // 拼错的接口路径不能被 SPA 兜底路由接住（那会把 POST 变成 405）。
        $this->postJson('/api/avatar/upload')->assertStatus(404);
        $this->postJson('/api/api/upload/avatar')->assertStatus(404);
    }
}
