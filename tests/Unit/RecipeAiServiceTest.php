<?php

namespace Tests\Unit;

use App\Services\RecipeAiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * RecipeAiService 单元测试：用 Http::fake 拦截 OpenAI 兼容接口，
 * 覆盖配置校验、JSON 解析（含 ```json 围栏）、食材硬约束的纠错重试、
 * 归一化后的食材是否正确进入 prompt，以及接口出错时的异常传播。
 *
 * 不依赖网络，也不依赖真实大模型。
 */
class RecipeAiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ai', [
            'base_uri' => 'https://api.example.com/v1',
            'api_key' => 'test-key',
            'model' => 'test-model',
            'timeout' => 30,
            'json_mode' => true,
        ]);
    }

    /** 构造一份合法的 chat/completions 响应体。 */
    private function fakeResponse(array $recipe): array
    {
        return [
            'choices' => [
                ['message' => ['content' => json_encode($recipe, JSON_UNESCAPED_UNICODE)]],
            ],
        ];
    }

    /** 从请求体的 messages 中取出 user 角色的内容（避免对 JSON 字符串做子串匹配）。 */
    private function userMessageContent(array $messages): string
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'user') {
                return (string) ($message['content'] ?? '');
            }
        }

        return '';
    }

    private function sampleRecipe(array $overrides = []): array
    {
        return array_merge([
            'name' => '番茄炒蛋',
            'description' => '一道经典家常菜',
            'category' => '家常菜',
            'ingredients' => [['name' => '鸡蛋', 'qty' => '2个'], ['name' => '番茄', 'qty' => '1个']],
            'directions' => [['description' => '打散鸡蛋'], ['description' => '下锅翻炒']],
        ], $overrides);
    }

    public function test_throws_when_not_configured(): void
    {
        Config::set('services.ai', [
            'base_uri' => null,
            'api_key' => null,
            'model' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI 服务未配置');

        (new RecipeAiService())->generate(['prompt' => '番茄炒蛋']);
    }

    public function test_parses_valid_response(): void
    {
        Http::fake([
            '*' => Http::response($this->fakeResponse($this->sampleRecipe()), 200),
        ]);

        $result = (new RecipeAiService())->generate(['prompt' => '番茄炒蛋']);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('番茄炒蛋', $result['name']);
        $this->assertNotEmpty($result['ingredients']);
        $this->assertNotEmpty($result['directions']);
        $this->assertArrayHasKey('category', $result);
    }

    public function test_parses_fenced_json(): void
    {
        $content = "```json\n".json_encode($this->sampleRecipe(), JSON_UNESCAPED_UNICODE)."\n```";
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => $content]]]], 200),
        ]);

        $result = (new RecipeAiService())->generate(['prompt' => '番茄炒蛋']);

        $this->assertSame('番茄炒蛋', $result['name']);
        $this->assertCount(2, $result['ingredients']);
    }

    public function test_retries_when_ingredient_missing(): void
    {
        $missing = $this->sampleRecipe([
            'ingredients' => [['name' => '土豆', 'qty' => '2个']], // 缺少「辣椒」
        ]);
        $complete = $this->sampleRecipe([
            'ingredients' => [['name' => '土豆', 'qty' => '2个'], ['name' => '辣椒', 'qty' => '1个']],
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode($missing, JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode($complete, JSON_UNESCAPED_UNICODE)]]]], 200),
        ]);

        // 指定食材 → 严格模式，缺失时应当发起第二次纠错请求
        $result = (new RecipeAiService())->generate(['ingredients' => '土豆、辣椒']);

        $names = array_column($result['ingredients'], 'name');
        $this->assertContains('辣椒', $names, '纠错后应把缺失食材补回结果');
        Http::assertSentCount(2);
    }

    public function test_passes_normalized_ingredients_into_prompt(): void
    {
        $captured = [];

        // 严格模式下服务会校验「返回结果必须包含指定食材」，因此让假响应带上它们，
        // 既能让首次请求即通过（无重试），又能断言归一化后的食材进入了 prompt。
        Http::fake(function ($request) use (&$captured) {
            $captured[] = json_decode((string) $request->body(), true);

            return Http::response($this->fakeResponse($this->sampleRecipe([
                'ingredients' => [
                    ['name' => '土豆', 'qty' => '2个'],
                    ['name' => '辣椒', 'qty' => '1个'],
                    ['name' => '鸡蛋', 'qty' => '2个'],
                ],
            ])), 200);
        });

        // 数组形式传入，应被归一为「土豆、辣椒」
        (new RecipeAiService())->generate(['ingredients' => ['土豆', '辣椒']]);

        $userContent = $this->userMessageContent($captured[0]['messages'] ?? []);
        $this->assertStringContainsString('土豆', $userContent);
        $this->assertStringContainsString('辣椒', $userContent);
    }

    public function test_string_ingredients_are_normalized_before_prompt(): void
    {
        $captured = [];

        Http::fake(function ($request) use (&$captured) {
            $captured[] = json_decode((string) $request->body(), true);

            return Http::response($this->fakeResponse($this->sampleRecipe([
                'ingredients' => [
                    ['name' => '土豆', 'qty' => '2个'],
                    ['name' => '辣椒', 'qty' => '1个'],
                    ['name' => '生姜', 'qty' => '1块'],
                ],
            ])), 200);
        });

        (new RecipeAiService())->generate(['ingredients' => '土豆，辣椒 和 生姜']);

        $userContent = $this->userMessageContent($captured[0]['messages'] ?? []);
        $this->assertStringContainsString('土豆', $userContent);
        $this->assertStringContainsString('辣椒', $userContent);
        $this->assertStringContainsString('生姜', $userContent);
    }

    public function test_throws_on_api_error(): void
    {
        Http::fake([
            '*' => Http::response('internal error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        (new RecipeAiService())->generate(['prompt' => '番茄炒蛋']);
    }
}
