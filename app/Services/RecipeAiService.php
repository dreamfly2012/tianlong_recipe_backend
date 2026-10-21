<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * 调用 OpenAI 兼容的 Chat Completions 接口，生成结构化菜谱数据。
 *
 * 配置（config/services.php + .env）：
 *  - AI_BASE_URI  接口地址，如 https://api.openai.com/v1 或本机 http://localhost:20128/v1
 *  - AI_API_KEY   密钥
 *  - AI_MODEL     模型名，如 gpt-4o-mini / deepseek-chat
 *  - AI_TIMEOUT   超时（秒，默认 60）
 *  - AI_JSON_MODE 是否启用 json_object 响应格式（默认 true；部分本地网关不支持可设为 false）
 *
 * 用法：
 *  - generate(['prompt' => '川味麻辣香锅'])        —— 按主题自由生成
 *  - generate(['ingredients' => '土豆,辣椒'])     —— 必须使用该食材生成（硬约束 + 纠错重试）
 *  - generate(['ingredients' => [...], 'prompt' => '家常口味']) —— 食材 + 风格要求
 *  也兼容：当用户只传 prompt 且内容像「食材清单」（含逗号/顿号/和/与）时，自动按食材约束处理。
 */
class RecipeAiService
{
    /**
     * 生成一份结构化菜谱。
     *
     * @param  array{prompt?:string, ingredients?:string|string[]}  $options
     * @return array{name:string, description:string, category:string, ingredients:array<int,array{name:string,qty:string}>, directions:array<int,array{description:string}>}
     * @throws RuntimeException
     */
    public function generate(array $options = []): array
    {
        // Web 环境（php-fpm / apache）常限制 max_execution_time（常见 30s），
        // 而大模型推理往往更久，这里放宽脚本执行时间，避免被引擎提前 kill。
        // Guzzle 的 timeout 只管 curl 层，管不了 PHP 引擎层的脚本时限。
        $maxTime = max((int) config('services.ai.timeout', 60) + 30, 120);
        @set_time_limit($maxTime);

        $baseUri = config('services.ai.base_uri');
        $apiKey = config('services.ai.api_key');
        $model = $options['model'] ?? config('services.ai.model');

        if (empty($baseUri) || empty($apiKey) || empty($model)) {
            throw new RuntimeException('AI 服务未配置，请在 .env 设置 AI_BASE_URI / AI_API_KEY / AI_MODEL');
        }

        [$ingredients, $theme] = $this->resolveInput(
            $options['ingredients'] ?? null,
            $options['prompt'] ?? ''
        );

        $strict = ! empty($ingredients);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($strict)],
            ['role' => 'user', 'content' => $this->buildPrompt($ingredients, $theme)],
        ];

        // 食材约束模式：降低随机性，保证忠实使用指定食材
        $temperature = $strict ? 0.4 : 0.8;
        $timeout = (int) config('services.ai.timeout', 60);
        $useJsonMode = (bool) config('services.ai.json_mode', true);

        $parsed = null;
        $missing = [];
        $maxAttempts = 2;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
            ];
            if ($useJsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            try {
                $response = Http::timeout($timeout)
                    ->withHeader('Authorization', 'Bearer '.$apiKey)
                    ->withHeader('Content-Type', 'application/json')
                    ->post(rtrim($baseUri, '/').'/chat/completions', $payload);

                if ($response->failed()) {
                    throw new RuntimeException('AI 接口返回错误：'.substr($response->body(), 0, 300));
                }

                $content = data_get($response->json(), 'choices.0.message.content', '');
            } catch (RequestException $e) {
                throw new RuntimeException('AI 接口请求异常：'.$e->getMessage());
            }

            $parsed = $this->parseRecipe((string) $content);

            // 仅食材约束模式下校验：指定食材必须出现在结果中
            if ($strict) {
                $missing = $this->missingIngredients($ingredients, $parsed['ingredients']);
                if (empty($missing)) {
                    break;
                }
                // 纠错：把遗漏的食材作为补充指令再问一次
                $messages[] = [
                    'role' => 'user',
                    'content' => '注意：你刚才生成的菜谱没有使用我指定的食材「'.implode('、', $missing).'」。'
                        .'请重新生成，必须把这些食材作为主料写入 ingredients 列表（可以搭配必要辅料与调味料，但不得换成其他主菜）。',
                ];
                continue;
            }

            break;
        }

        if ($strict && ! empty($missing)) {
            throw new RuntimeException('AI 返回未包含指定食材：'.implode('、', $missing).'，请换一个模型或简化食材清单后重试');
        }

        return $parsed;
    }

    /**
     * 把入参解析为 [食材数组, 主题字符串]。
     * 规则：
     *  - 显式传入 ingredients 时优先作为食材约束；
     *  - 否则若 prompt 看起来像「食材清单」（含分隔符/和/与），自动当作食材处理。
     */
    private function resolveInput(mixed $rawIngredients, string $prompt): array
    {
        $ingredients = $this->normalizeIngredients($rawIngredients);

        if (! empty($ingredients)) {
            return [$ingredients, trim($prompt)];
        }

        $prompt = trim($prompt);
        if ($prompt !== '' && $this->looksLikeIngredients($prompt)) {
            return [$this->normalizeIngredients($prompt), ''];
        }

        return [[], $prompt];
    }

    /**
     * 将字符串/数组食材统一为数组。
     * 支持中文顿号、逗号、斜杠、空格、「和/与/加」连接词。
     *
     * @param  string|string[]|null  $raw
     * @return string[]
     */
    private function normalizeIngredients(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            $pieces = $raw;
        } else {
            $text = (string) $raw;
            // 先把「和/与/加」连接词替换为顿号，再按常见分隔符切分
            $text = preg_replace('/和|与|加|配|搭/', '、', $text);
            $pieces = preg_split('/[、，,;\/\s]+/u', $text);
        }

        $result = [];
        foreach ($pieces as $p) {
            $p = trim((string) $p);
            // 过滤明显的量词/空串（如「适量」「一些」）
            if ($p === '' || in_array($p, ['适量', '一些', '少许', '若干'], true)) {
                continue;
            }
            $result[] = $p;
        }

        return array_values(array_unique($result));
    }

    /**
     * 粗略判断一段文本是否像「食材清单」而非菜名/主题。
     */
    private function looksLikeIngredients(string $text): bool
    {
        if (preg_match('/[、，,;]/u', $text)) {
            return true;
        }
        if (preg_match('/(和|与|加|配|搭)/u', $text)) {
            return true;
        }

        return false;
    }

    private function systemPrompt(bool $strict): string
    {
        $base = <<<'PROMPT'
你是一个专业的中餐食谱生成助手。请根据用户要求生成一份完整、可操作的食谱，并以严格的 JSON 对象返回，不要包含任何额外说明文字或 Markdown 代码块标记。

JSON 结构必须如下：
{
  "name": "菜谱名称",
  "description": "一句话简介（不超过 80 字）",
  "category": "分类名称，如：川菜、家常菜、汤羹、甜点、烘焙、凉菜",
  "ingredients": [
    {"name": "食材名", "qty": "用量，如 200克 / 1个 / 适量"}
  ],
  "directions": [
    {"description": "第 1 步操作说明"},
    {"description": "第 2 步操作说明"}
  ]
}

要求：
- ingredients 至少 3 项，directions 至少 3 步；
- 步骤要具体、可执行、符合真实烹饪逻辑；
- category 尽量使用常见中餐分类。
PROMPT;

        if ($strict) {
            $base .= <<<'PROMPT'

【强制约束】用户已指定要使用的食材。你必须：
- 把用户指定的每种食材都作为「主料」写入 ingredients 列表，不得遗漏、不得替换成其他主菜；
- 例如用户给定「土豆、辣椒」，就做一道以土豆和辣椒为主料的菜（如酸辣土豆丝、青椒土豆片），绝不能生成宫保鸡丁、红烧肉之类不含这些主料的菜；
- 可以按需补充必要的辅料、调料与烹饪步骤，但主料必须是用户指定的那些。
PROMPT;
        }

        return $base;
    }

    private function buildPrompt(array $ingredients, string $theme): string
    {
        if (! empty($ingredients)) {
            $list = implode('、', $ingredients);
            $text = '请用以下食材作为主料生成一道菜谱：'.$list
                .'。这些食材必须全部出现在 ingredients 列表中并作为主料，可搭配必要辅料与调味料。';
            if ($theme !== '') {
                $text .= '风格/额外要求：'.$theme.'。';
            }

            return $text;
        }

        $theme = trim($theme);
        if ($theme === '') {
            return '请随机生成一道受欢迎的家常菜食谱。';
        }

        return '请生成一道关于「'.$theme.'」的食谱。';
    }

    /**
     * 检查用户指定食材是否都出现在生成结果中（子串匹配，忽略量词）。
     *
     * @param  string[]  $userIngredients
     * @param  array<int,array{name:string,qty:string}>  $returned
     * @return string[]
     */
    private function missingIngredients(array $userIngredients, array $returned): array
    {
        $haystack = '';
        foreach ($returned as $ing) {
            $haystack .= ($ing['name'] ?? '').' ';
        }
        $haystack = (string) $haystack;

        $missing = [];
        foreach ($userIngredients as $need) {
            if (mb_stripos($haystack, $need) === false) {
                $missing[] = $need;
            }
        }

        return $missing;
    }

    /**
     * 解析并校验模型返回的 JSON，统一规整字段。
     *
     * @throws RuntimeException
     */
    private function parseRecipe(string $content): array
    {
        $content = trim($content);

        // 去掉可能的 ```json ... ``` 围栏
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) {
            $content = $m[1];
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            // 模型可能夹带说明文字，尝试抽取第一个 {...} 对象（支持嵌套）
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $m2)) {
                $data = json_decode($m2[0], true);
            }
        }

        if (! is_array($data)) {
            throw new RuntimeException('AI 返回内容无法解析为 JSON：'.substr($content, 0, 200));
        }

        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $category = trim((string) ($data['category'] ?? '家常菜'));

        $ingredients = [];
        foreach (array_slice((array) ($data['ingredients'] ?? []), 0, 30) as $ing) {
            if (! is_array($ing)) {
                continue;
            }
            $n = trim((string) ($ing['name'] ?? ''));
            $q = trim((string) ($ing['qty'] ?? ''));
            if ($n !== '') {
                $ingredients[] = ['name' => $n, 'qty' => $q ?: '适量'];
            }
        }

        $directions = [];
        foreach (array_slice((array) ($data['directions'] ?? []), 0, 30) as $dir) {
            if (is_string($dir)) {
                $d = trim($dir);
            } else {
                $d = trim((string) (is_array($dir) ? ($dir['description'] ?? '') : ''));
            }
            if ($d !== '') {
                $directions[] = ['description' => $d];
            }
        }

        if ($name === '' || $ingredients === [] || $directions === []) {
            throw new RuntimeException('AI 返回的食谱字段不完整（缺少名称/食材/步骤）');
        }

        return [
            'name' => mb_substr($name, 0, 255),
            'description' => mb_substr($description, 0, 3000),
            'category' => mb_substr($category, 0, 255),
            'ingredients' => $ingredients,
            'directions' => $directions,
        ];
    }
}
