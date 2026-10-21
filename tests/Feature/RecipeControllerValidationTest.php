<?php

namespace Tests\Feature;

use App\Models\RecipeCategory;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 针对 RecipeController 的「写入校验」分支补充测试。
 * 既有 RecipeApiTest 已覆盖正常创建/更新，这里专门压校验失败路径与字段级规则，
 * 防止 validate 规则被误改时悄悄放行脏数据。
 */
class RecipeControllerValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RecipeCategory $category;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        Storage::fake('cos');

        $this->user = User::factory()->create();
        $this->category = RecipeCategory::create([
            'name' => '川菜',
            'description' => '麻辣鲜香',
            'parent_id' => 0,
        ]);
        $this->recipe = $this->user->recipes()->create([
            'name' => '番茄炒蛋',
            'description' => '一道经典的中式家常菜',
            'image' => 'test_image.jpg',
            'category_id' => $this->category->id,
        ]);
    }

    /** 一份能通过校验的合法创建载荷，便于在单项校验用例中复用 / 局部覆盖。 */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '新食谱',
            'description' => '测试描述',
            'category_id' => $this->category->id,
            'image' => UploadedFile::fake()->image('new-recipe.jpg'),
            'ingredients' => [['name' => '鸡蛋', 'qty' => '2个']],
            'directions' => [['description' => '第一步']],
        ], $overrides);
    }

    // ========== 创建校验 ==========

    public function test_store_requires_description(): void
    {
        Sanctum::actingAs($this->user);

        $payload = $this->validStorePayload();
        unset($payload['description']);

        $this->postJson('/api/recipes', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_store_requires_non_empty_ingredients(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/recipes', $this->validStorePayload(['ingredients' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ingredients');
    }

    public function test_store_requires_non_empty_directions(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/recipes', $this->validStorePayload(['directions' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('directions');
    }

    public function test_store_rejects_invalid_category(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/recipes', $this->validStorePayload(['category_id' => 9999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_store_rejects_missing_ingredient_fields(): void
    {
        Sanctum::actingAs($this->user);

        // ingredients.*.name / qty 均为 required
        $this->postJson('/api/recipes', $this->validStorePayload([
            'ingredients' => [['name' => '', 'qty' => '']],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['ingredients.0.name', 'ingredients.0.qty']);
    }

    public function test_store_rejects_missing_direction_description(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/recipes', $this->validStorePayload([
            'directions' => [['description' => '']],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('directions.0.description');
    }

    public function test_store_rejects_non_image_file(): void
    {
        Sanctum::actingAs($this->user);

        // 传一个 PDF，触发 image|mimes 规则
        $payload = $this->validStorePayload([
            'image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'),
        ]);

        $this->postJson('/api/recipes', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_store_rejects_oversized_image(): void
    {
        Sanctum::actingAs($this->user);

        // 超过 2048 KB 上限
        $payload = $this->validStorePayload([
            'image' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ]);

        $this->postJson('/api/recipes', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    // ========== 更新校验 ==========

    /** 一份能通过校验的合法更新载荷（含已有子资源 id，用于覆盖同步逻辑）。 */
    private function validUpdatePayload(array $overrides = []): array
    {
        $ingredient = $this->recipe->ingredients()->create(['name' => '鸡蛋', 'qty' => '3个']);
        $direction = $this->recipe->directions()->create(['description' => '第一步']);

        return array_merge([
            'name' => '修改后的名称',
            'description' => '新的描述',
            'category_id' => $this->category->id,
            'ingredients' => [['id' => $ingredient->id, 'name' => '鸡蛋', 'qty' => '5个']],
            'directions' => [['id' => $direction->id, 'description' => '新步骤']],
        ], $overrides);
    }

    public function test_update_rejects_invalid_category(): void
    {
        Sanctum::actingAs($this->user);

        // validate 先于归属查询执行，非法 category_id 应返回 422 而非 404
        $this->putJson('/api/recipes/'.$this->recipe->id, $this->validUpdatePayload(['category_id' => 9999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_update_requires_name(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/recipes/'.$this->recipe->id, $this->validUpdatePayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_unknown_ingredient_id(): void
    {
        Sanctum::actingAs($this->user);

        // ingredients.*.id 必须是已存在的 recipe_ingredients 记录
        $this->putJson('/api/recipes/'.$this->recipe->id, $this->validUpdatePayload([
            'ingredients' => [['id' => 999999, 'name' => '幽灵食材', 'qty' => '1个']],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('ingredients.0.id');
    }

    public function test_update_skips_image_validation_when_absent(): void
    {
        Sanctum::actingAs($this->user);

        // image 是 sometimes 规则，未传图片时不应报 422
        $this->putJson('/api/recipes/'.$this->recipe->id, $this->validUpdatePayload())
            ->assertStatus(200)
            ->assertJsonPath('code', 0);
    }
}
