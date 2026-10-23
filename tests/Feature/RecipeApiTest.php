<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeDirection;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\RecipeAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private RecipeCategory $category;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        Storage::fake('cos');

        $this->seedData();
    }

    private function seedData(): void
    {
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'is_admin' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
        ]);

        $this->category = RecipeCategory::create([
            'name' => '川菜',
            'description' => '麻辣鲜香',
            'parent_id' => 0,
        ]);

        $this->recipe = $this->admin->recipes()->create([
            'name'         => '番茄炒蛋',
            'description'  => '一道经典的中式家常菜',
            'image'        => 'test_image.jpg',
            'category_id'  => $this->category->id,
            'cooking_time' => 15,
            'difficulty'   => '简单',
            'tags'         => '家常,快手',
        ]);

        RecipeIngredient::create([
            'recipe_id' => $this->recipe->id,
            'name' => '鸡蛋',
            'qty' => '3个',
        ]);

        RecipeDirection::create([
            'recipe_id' => $this->recipe->id,
            'description' => '鸡蛋打散，番茄切块',
        ]);
    }

    // ========== 食谱列表 ==========

    public function test_can_get_recipes_list(): void
    {
        $response = $this->getJson('/api/recipe.lists');

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋')
            ->assertJsonPath('data.data.0.cooking_time', 15)
            ->assertJsonPath('data.data.0.difficulty', '简单')
            ->assertJsonPath('data.data.0.tags', '家常,快手');
    }

    public function test_recipes_list_is_filtered_by_category(): void
    {
        $other = RecipeCategory::create([
            'name' => '粤菜',
            'description' => '清淡',
            'parent_id' => 0,
        ]);

        $this->admin->recipes()->create([
            'name'         => '白切鸡',
            'description'  => '粤菜经典',
            'image'        => 'chicken.jpg',
            'category_id'  => $other->id,
            'cooking_time' => 30,
            'difficulty'   => '中等',
            'tags'         => null,
        ]);

        $response = $this->getJson('/api/recipe.lists?id='.$this->category->id);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }

    public function test_recipes_list_does_not_reuse_first_page_cache_for_other_pages(): void
    {
        Recipe::factory()->count(20)->create(['user_id' => $this->admin->id]);

        $first = $this->getJson('/api/recipe.lists?page=1')->json('data.data');
        $second = $this->getJson('/api/recipe.lists?page=2')->json('data.data');

        $this->assertNotEquals(
            array_column($first, 'id'),
            array_column($second, 'id')
        );
    }

    // ========== 食谱搜索 ==========

    public function test_can_search_recipes(): void
    {
        $response = $this->getJson('/api/recipe.search?name=番茄');

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }

    public function test_search_returns_empty_when_not_found(): void
    {
        $response = $this->getJson('/api/recipe.search?name=不存在');

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 0);
    }

    // ========== 食谱详情 ==========

    public function test_can_get_recipe_info(): void
    {
        $response = $this->getJson('/api/recipe.info?id='.$this->recipe->id);

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', '番茄炒蛋')
            ->assertJsonPath('data.category.name', '川菜')
            ->assertJsonPath('data.ingredients.0.name', '鸡蛋');
    }

    public function test_recipe_image_src_uses_cos_cdn_when_configured(): void
    {
        config([
            'filesystems.disks.cos.secret_id' => 'test-secret-id',
            'filesystems.disks.cos.secret_key' => 'test-secret-key',
            'filesystems.disks.cos.bucket' => 'test-bucket',
            'filesystems.disks.cos.cdn' => 'https://cdn.example.com/',
        ]);

        $this->getJson('/api/recipe.info?id='.$this->recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('data.image_src', 'https://cdn.example.com/test_image.jpg');
    }

    public function test_recipe_image_src_falls_back_to_local_url_without_cos(): void
    {
        config([
            'filesystems.disks.cos.secret_id' => null,
            'filesystems.disks.cos.secret_key' => null,
            'filesystems.disks.cos.bucket' => null,
            'filesystems.disks.cos.cdn' => null,
        ]);

        $this->getJson('/api/recipe.info?id='.$this->recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('data.image_src', '/images/test_image.jpg');
    }

    public function test_recipe_info_returns_error_for_missing_id(): void
    {
        $response = $this->getJson('/api/recipe.info');

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 2);
    }

    public function test_recipe_info_returns_not_found_for_invalid_id(): void
    {
        $response = $this->getJson('/api/recipe.info?id=999');

        $response
            ->assertStatus(404)
            ->assertJsonPath('code', 404);
    }

    // ========== 分类接口 ==========

    public function test_can_get_categories(): void
    {
        $response = $this->getJson('/api/recipe.category');

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.0.name', '川菜');
    }

    public function test_can_get_subcategories(): void
    {
        RecipeCategory::create([
            'name' => '辣菜',
            'description' => '更辣',
            'parent_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/recipe.category?parent_id='.$this->category->id);

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.0.name', '辣菜');
    }

    // ========== 认证接口 ==========

    public function test_can_register_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.user.email', 'new@test.com');

        $this->assertNotNull($response->json('data.api_token'));
        $this->assertDatabaseHas('users', ['email' => 'new@test.com']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Duplicate',
            'email' => $this->admin->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_can_login_user(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.user.email', $this->admin->email);

        $this->assertNotEmpty($response->json('data.api_token'));
        $this->assertSame($response->json('data.api_token'), $response->json('data.token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => 'wrong_password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 1);
    }

    public function test_can_logout_user(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/logout');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.done', true);
    }

    public function test_logout_revokes_personal_access_token(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);

        $token = $login->json('data.api_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_cannot_logout_without_auth(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    // ========== 受保护的食谱操作 ==========

    public function test_cannot_create_recipe_without_auth(): void
    {
        $this->postJson('/api/recipes')->assertStatus(401);
    }

    public function test_can_create_recipe(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->post('/api/recipes', [
            'name' => '新食谱',
            'description' => '测试描述',
            'category_id' => $this->category->id,
            'image' => UploadedFile::fake()->image('new-recipe.jpg'),
            'ingredients' => [['name' => '鸡蛋', 'qty' => '2个']],
            'directions' => [['description' => '第一步']],
        ], ['Accept' => 'application/json']);

        $response
            ->assertStatus(201)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.id', fn ($id) => is_int($id));

        $recipe = Recipe::where('name', '新食谱')->firstOrFail();

        $this->assertSame($this->admin->id, $recipe->user_id);
        $this->assertTrue(Storage::disk('images')->exists($recipe->image));
        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $recipe->id,
            'name' => '鸡蛋',
        ]);
        $this->assertDatabaseHas('recipe_directions', [
            'recipe_id' => $recipe->id,
            'description' => '第一步',
        ]);
    }

    public function test_create_recipe_requires_name(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/recipes', [
            'description' => '测试',
            'category_id' => $this->category->id,
            'ingredients' => [],
            'directions' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'image']);
    }

    public function test_can_update_recipe_and_sync_children(): void
    {
        Sanctum::actingAs($this->admin);

        $existingIngredient = $this->recipe->ingredients()->first();
        $existingDirection = $this->recipe->directions()->first();

        $response = $this->putJson('/api/recipes/'.$this->recipe->id, [
            'name' => '修改后的名称',
            'description' => '新的描述',
            'category_id' => $this->category->id,
            'ingredients' => [
                ['id' => $existingIngredient->id, 'name' => '鸡蛋', 'qty' => '5个'],
                ['name' => '番茄', 'qty' => '2个'],
            ],
            'directions' => [
                ['id' => $existingDirection->id, 'description' => '新步骤'],
                ['description' => '第二步'],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('code', 0);

        $this->assertDatabaseHas('recipes', [
            'id' => $this->recipe->id,
            'name' => '修改后的名称',
        ]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'id' => $existingIngredient->id,
            'qty' => '5个',
        ]);
        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $this->recipe->id,
            'name' => '番茄',
        ]);
        $this->assertSame(2, $this->recipe->ingredients()->count());

        $this->assertDatabaseHas('recipe_directions', [
            'id' => $existingDirection->id,
            'description' => '新步骤',
        ]);
        $this->assertSame(2, $this->recipe->directions()->count());
    }

    public function test_can_remove_omitted_children_on_update(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/recipes/'.$this->recipe->id, [
            'name' => '只剩一个配料',
            'description' => '测试',
            'category_id' => $this->category->id,
            'ingredients' => [
                ['name' => '番茄', 'qty' => '2个'],
            ],
            'directions' => [
                ['description' => '唯一步骤'],
            ],
        ])->assertStatus(200);

        $this->assertSame(1, $this->recipe->ingredients()->count());
        $this->assertSame(1, $this->recipe->directions()->count());
        $this->assertDatabaseMissing('recipe_ingredients', [
            'recipe_id' => $this->recipe->id,
            'name' => '鸡蛋',
        ]);
    }

    public function test_cannot_update_others_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/recipes/'.$this->recipe->id, [
            'name' => '尝试修改',
            'description' => '测试',
            'category_id' => $this->category->id,
            'ingredients' => [['name' => '鸡蛋', 'qty' => '1个']],
            'directions' => [['description' => '步骤']],
        ])->assertStatus(404);
    }

    public function test_can_delete_recipe_and_image(): void
    {
        Sanctum::actingAs($this->admin);

        $image = $this->recipe->image;
        Storage::disk('images')->put($image, 'fake');

        $this->deleteJson('/api/recipes/'.$this->recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        $this->assertDatabaseMissing('recipes', ['id' => $this->recipe->id]);
        $this->assertFalse(Storage::disk('images')->exists($image));
    }

    public function test_cannot_delete_others_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/recipes/'.$this->recipe->id)->assertStatus(404);
    }

    // ========== 收藏功能 ==========

    public function test_can_favorite_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('recipe_user', [
            'user_id' => $this->user->id,
            'recipe_id' => $this->recipe->id,
        ]);
    }

    public function test_favoriting_twice_does_not_create_duplicates(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id);
        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id);

        $this->assertDatabaseCount('recipe_user', 1);
    }

    public function test_can_unfavorite_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id);

        $this->getJson('/api/recipe.unfavourite?recipe_id='.$this->recipe->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('recipe_user', [
            'user_id' => $this->user->id,
            'recipe_id' => $this->recipe->id,
        ]);
    }

    public function test_can_check_if_recipe_is_favorite(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.isfavourite?recipe_id='.$this->recipe->id)
            ->assertJsonPath('data.isFavorite', false);

        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id);

        $this->getJson('/api/recipe.isfavourite?recipe_id='.$this->recipe->id)
            ->assertJsonPath('data.isFavorite', true);
    }

    public function test_can_get_favorite_list(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.favourite?recipe_id='.$this->recipe->id);

        $this->getJson('/api/recipe.favourites')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }

    public function test_favorite_requires_existing_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipe.favourite?recipe_id=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipe_id');
    }

    // ========== 每日推荐等 ==========

    public function test_can_get_daily_recipes(): void
    {
        $this->getJson('/api/recipe.daily')
            ->assertStatus(200)
            ->assertJsonPath('code', 0);
    }

    public function test_can_get_ai_recipes(): void
    {
        $this->getJson('/api/recipe.ai')
            ->assertStatus(200)
            ->assertJsonPath('code', 0);
    }

    public function test_can_get_personalized_recipes(): void
    {
        $this->getJson('/api/recipe.personalized')
            ->assertStatus(200)
            ->assertJsonPath('code', 0);
    }

    public function test_can_generate_recipe(): void
    {
        // 用假服务替代真实大模型调用，验证落库与返回结构
        $fake = new class {
            public function generate(array $options = []): array
            {
                return [
                    'name' => 'AI 生成的测试菜',
                    'description' => '由大模型生成',
                    'category' => '测试分类',
                    'ingredients' => [
                        ['name' => '鸡蛋', 'qty' => '2个'],
                        ['name' => '番茄', 'qty' => '1个'],
                    ],
                    'directions' => [
                        ['description' => '步骤一'],
                        ['description' => '步骤二'],
                    ],
                ];
            }
        };
        $this->app->bind(RecipeAiService::class, fn () => $fake);

        $this->postJson('/api/recipe.generate', ['prompt' => '番茄炒蛋'])
            ->assertStatus(201)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', 'AI 生成的测试菜')
            ->assertJsonPath('data.category.name', '测试分类')
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonCount(2, 'data.directions');

        $this->assertDatabaseHas('recipes', ['name' => 'AI 生成的测试菜']);
        $this->assertDatabaseHas('recipe_ingredients', ['name' => '鸡蛋']);
    }

    public function test_generate_returns_error_when_ai_not_configured(): void
    {
        // 确保使用真实服务（清除可能存在的测试期绑定）并置空配置
        app()->forgetInstance(RecipeAiService::class);
        Config::set('services.ai.base_uri', null);
        Config::set('services.ai.api_key', null);
        Config::set('services.ai.model', null);

        $this->postJson('/api/recipe.generate')
            ->assertStatus(500)
            ->assertJsonPath('code', 500);
    }

    public function test_generate_reuses_existing_recipe_for_same_prompt(): void
    {
        $fake = new class {
            public function generate(array $options = []): array
            {
                return [
                    'name' => '复用测试菜',
                    'description' => '应被复用',
                    'category' => '测试分类',
                    'ingredients' => [['name' => '鸡蛋', 'qty' => '2个']],
                    'directions' => [['description' => '步骤一']],
                ];
            }
        };
        $this->app->bind(RecipeAiService::class, fn () => $fake);

        $first = $this->postJson('/api/recipe.generate', ['prompt' => '复用测试菜'])
            ->assertStatus(201)
            ->json('data');

        $second = $this->postJson('/api/recipe.generate', ['prompt' => '复用测试菜'])
            ->assertStatus(200)
            ->assertJsonPath('message', '已从已有生成中获取')
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
    }

    public function test_generate_passes_ingredients_to_service_and_records_cache_key(): void
    {
        $captured = [];
        $fake = new class($captured) {
            public array $captured;
            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }
            public function generate(array $options = []): array
            {
                $this->captured = $options;

                return [
                    'name' => '青椒土豆丝',
                    'description' => '按食材生成的测试菜',
                    'category' => '家常菜',
                    'ingredients' => [['name' => '土豆', 'qty' => '2个'], ['name' => '青椒', 'qty' => '1个']],
                    'directions' => [['description' => '步骤一'], ['description' => '步骤二']],
                ];
            }
        };
        $this->app->bind(RecipeAiService::class, fn () => $fake);

        $this->postJson('/api/recipe.generate', ['ingredients' => '土豆,辣椒', 'prompt' => '家常'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', '青椒土豆丝');

        // 食材应透传给服务（控制器只做类型归一，由服务进一步切分）
        $this->assertSame('土豆,辣椒', $captured['ingredients'] ?? null);
        $this->assertSame('家常', $captured['prompt'] ?? null);
        // ai_prompt 缓存键应包含食材与主题组合
        $this->assertDatabaseHas('recipes', ['ai_prompt' => '食材:土豆,辣椒 | 主题:家常']);
    }

    public function test_generate_reuses_existing_recipe_for_same_ingredients(): void
    {
        $fake = new class {
            public function generate(array $options = []): array
            {
                return [
                    'name' => '复用食材测试菜',
                    'description' => '应被复用',
                    'category' => '家常菜',
                    'ingredients' => [['name' => '土豆', 'qty' => '2个']],
                    'directions' => [['description' => '步骤一']],
                ];
            }
        };
        $this->app->bind(RecipeAiService::class, fn () => $fake);

        $first = $this->postJson('/api/recipe.generate', ['ingredients' => '土豆、辣椒'])
            ->assertStatus(201)
            ->json('data');

        $second = $this->postJson('/api/recipe.generate', ['ingredients' => '土豆、辣椒'])
            ->assertStatus(200)
            ->assertJsonPath('message', '已从已有生成中获取')
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
    }

    // ========== 小程序登录 ==========

    public function test_can_wechat_login_with_mock(): void
    {
        Config::set('services.wechat_miniprogram.appid', null);
        Config::set('services.wechat_miniprogram.secret', null);

        $response = $this->postJson('/api/wechat/login', [
            'code' => 'mock_code_123',
            'user_info' => [
                'nickName' => '微信用户',
                'avatarUrl' => 'http://example.com/avatar.jpg',
            ],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.authenticated', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['name' => '微信用户']);
    }

    // ========== CRUD 表单接口 ==========

    public function test_can_get_form_template(): void
    {
        $this->getJson('/api/recipe.create')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.form.name', '')
            ->assertJsonPath('data.categories.0.name', '川菜');
    }

    public function test_can_show_recipe(): void
    {
        $this->getJson('/api/recipes/'.$this->recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', '番茄炒蛋')
            ->assertJsonPath('data.category.name', '川菜');
    }

    public function test_can_get_edit_form_for_owner(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/recipes/'.$this->recipe->id.'/edit')
            ->assertStatus(200)
            ->assertJsonPath('data.form.name', '番茄炒蛋')
            ->assertJsonPath('data.form.ingredients.0.name', '鸡蛋');
    }

    public function test_cannot_get_edit_form_for_others_recipe(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/recipes/'.$this->recipe->id.'/edit')
            ->assertStatus(404);
    }

    public function test_api_index_returns_paginated_recipes(): void
    {
        $this->getJson('/api/recipes')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }
}
