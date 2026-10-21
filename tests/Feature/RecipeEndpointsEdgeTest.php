<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\User;
use App\Services\RecipeAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 针对 RecipeController 公开 / 半公开接口的「边界与内容」补充测试。
 * 既有 RecipeApiTest 已覆盖核心流程，这里补全：
 *  - 列表 / 搜索 / 分类的空结果与全量返回
 *  - show 的 404 分支
 *  - 收藏列表的内容正确性与鉴权
 *  - daily / ai / personalized 的返回内容（不只是 200）
 *  - generate 在无输入时的行为（ai_prompt 应为 null）
 *  - destroy 同时解绑收藏关系
 */
class RecipeEndpointsEdgeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $fan;
    private RecipeCategory $category;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->fan = User::factory()->create();
        $this->category = RecipeCategory::create([
            'name' => '川菜',
            'description' => '麻辣鲜香',
            'parent_id' => 0,
        ]);
        $this->recipe = $this->owner->recipes()->create([
            'name' => '番茄炒蛋',
            'description' => '一道经典的中式家常菜',
            'image' => 'test_image.jpg',
            'category_id' => $this->category->id,
        ]);
    }

    // ========== 搜索 / 分类 的空结果与全量 ==========

    public function test_search_without_name_returns_all_recipes(): void
    {
        Recipe::factory()->count(3)->create(['user_id' => $this->owner->id]);

        $this->getJson('/api/recipe.search')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 4);
    }

    public function test_category_returns_empty_for_parent_with_no_children(): void
    {
        // 新建一个没有任何子分类的父级
        $lonely = RecipeCategory::create([
            'name' => '孤类',
            'description' => '',
            'parent_id' => 0,
        ]);

        $this->getJson('/api/recipe.category?parent_id='.$lonely->id)
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJson([]); // data 为空数组
    }

    // ========== show 的 404 分支 ==========

    public function test_show_returns_404_for_missing_recipe(): void
    {
        $this->getJson('/api/recipes/999999')
            ->assertStatus(404)
            ->assertJsonPath('code', 404);
    }

    // ========== 收藏列表内容与鉴权 ==========

    public function test_favorite_list_returns_only_the_users_favorites(): void
    {
        $other = $this->owner->recipes()->create([
            'name' => '只属于主人的菜',
            'description' => 'desc',
            'image' => 'x.jpg',
            'category_id' => $this->category->id,
        ]);

        // fan 只收藏了这一个
        $this->fan->favoriteRecipes()->attach($this->recipe->id);

        Sanctum::actingAs($this->fan);

        $this->getJson('/api/recipe.favourites')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋')
            ->assertJsonCount(1, 'data.data');
    }

    public function test_favorite_list_requires_auth(): void
    {
        $this->getJson('/api/recipe.favourites')->assertStatus(401);
    }

    // ========== daily / ai / personalized 返回内容 ==========

    public function test_daily_contains_seeded_recipe(): void
    {
        $this->getJson('/api/recipe.daily')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }

    public function test_ai_contains_seeded_recipe(): void
    {
        $this->getJson('/api/recipe.ai')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.data.0.name', '番茄炒蛋');
    }

    public function test_personalized_uses_small_page_size(): void
    {
        // 再建两个菜，总计 3 个；personalized 分页大小为 2
        Recipe::factory()->count(2)->create(['user_id' => $this->owner->id]);

        $this->getJson('/api/recipe.personalized')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonCount(2, 'data.data');
    }

    // ========== generate 无输入 ==========

    public function test_generate_with_no_input_creates_recipe_with_null_prompt(): void
    {
        // 绑定一个假服务，验证「无食材无主题」时仍落库，且 ai_prompt 为 null
        $fake = new class {
            public function generate(array $options = []): array
            {
                return [
                    'name' => '随意菜',
                    'description' => '无输入生成的菜',
                    'category' => '家常菜',
                    'ingredients' => [['name' => '盐', 'qty' => '适量']],
                    'directions' => [['description' => '随便炒']],
                ];
            }
        };
        $this->app->bind(RecipeAiService::class, fn () => $fake);

        $this->postJson('/api/recipe.generate', [])
            ->assertStatus(201)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', '随意菜');

        $this->assertDatabaseHas('recipes', ['name' => '随意菜', 'ai_prompt' => null]);
    }

    public function test_generate_returns_server_error_when_service_throws(): void
    {
        // 真实服务未配置时应抛 RuntimeException -> 500
        app()->forgetInstance(RecipeAiService::class);
        Config::set('services.ai.base_uri', null);
        Config::set('services.ai.api_key', null);
        Config::set('services.ai.model', null);

        $this->postJson('/api/recipe.generate', ['prompt' => '番茄炒蛋'])
            ->assertStatus(500)
            ->assertJsonPath('code', 500);
    }

    // ========== destroy 解绑收藏 ==========

    public function test_destroy_detaches_favorites(): void
    {
        // fan 收藏了 owner 的菜
        $this->fan->favoriteRecipes()->attach($this->recipe->id);
        $this->assertDatabaseHas('recipe_user', [
            'user_id' => $this->fan->id,
            'recipe_id' => $this->recipe->id,
        ]);

        Sanctum::actingAs($this->owner);

        $this->deleteJson('/api/recipes/'.$this->recipe->id)
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        // 收藏关系应被解绑
        $this->assertDatabaseMissing('recipe_user', [
            'user_id' => $this->fan->id,
            'recipe_id' => $this->recipe->id,
        ]);
        $this->assertDatabaseMissing('recipes', ['id' => $this->recipe->id]);
    }
}
