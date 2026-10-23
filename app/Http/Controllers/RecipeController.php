<?php

namespace App\Http\Controllers;

use App\Http\Helpers\ApiResponse;
use App\Services\BingImageService;
use App\Services\CosService;
use App\Services\RecipeAiService;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="天龙食谱 API 文档",
 *      description="天龙食谱后端 API 接口文档"
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT"
 * )
 */
class RecipeController extends Controller
{
    /** 缓存有效期（秒） */
    private const CACHE_TTL = 600;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except([
            'index', 'show', 'lists', 'info', 'category', 'search',
            'daily', 'ai', 'personalized', 'generate', 'create',
        ]);
    }

    /**
     * 获取食谱列表（支持按分类筛选）
     *
     * @OA\Get(
     *      path="/api/recipe.lists",
     *      operationId="getRecipesList",
     *      tags={"Recipes"},
     *      summary="获取食谱列表",
     *      description="返回所有食谱或根据分类ID获取食谱列表",
     *      @OA\Parameter(
     *          name="id",
     *          description="分类ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(response=200, description="成功操作"),
     *      @OA\Response(response=400, description="无效请求")
     * )
     */
    public function lists(Request $request)
    {
        $categoryId = (int) $request->get('id', 0);
        $page = max(1, (int) $request->get('page', 1));
        $key = 'recipes:list:'.($categoryId ?: 'all').':page:'.$page;

        $data = $this->remember('recipes', $key, function () use ($categoryId) {
            $query = Recipe::query();

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            return $query->orderByDesc('id')->paginate()->through(function ($recipe) {
                return [
                    'id'           => $recipe->id,
                    'name'         => $recipe->name,
                    'description'  => $recipe->description,
                    'image'        => $recipe->image,
                    'image_src'    => $recipe->image_src,
                    'category_id'  => $recipe->category_id,
                    'user_id'      => $recipe->user_id,
                    'cooking_time' => $recipe->cooking_time,
                    'difficulty'   => $recipe->difficulty,
                    'tags'         => $recipe->tags,
                    'created_at'   => $recipe->created_at,
                    'updated_at'   => $recipe->updated_at,
                ];
            })->toArray();
        });

        return ApiResponse::success($data, '获取食谱列表成功');
    }

    /**
     * 每日推荐食谱
     */
    public function daily(Request $request)
    {
        $recipes = Recipe::orderByDesc('id')->paginate(15);

        return ApiResponse::success($recipes, '获取每日推荐食谱成功');
    }

    /**
     * AI 推荐食谱
     */
    public function ai(Request $request)
    {
        $recipes = Recipe::orderByDesc('id')->paginate(15);

        return ApiResponse::success($recipes, '获取AI推荐食谱成功');
    }

    /**
     * 个性化推荐食谱
     */
    public function personalized(Request $request)
    {
        $recipes = Recipe::orderByDesc('id')->paginate(2);

        return ApiResponse::success($recipes, '获取个性化推荐食谱成功');
    }

    /**
     * AI 生成食谱：调用大模型实时生成一份完整菜谱并落库为新记录。
     *
     * 可选请求参数：
     *  - ingredients：指定食材（字符串或数组，如「土豆,辣椒」/["土豆","辣椒"]），将作为主料硬约束
     *  - prompt：主题/风格要求（如「川味麻辣香锅」「少油清淡」）
     */
    public function generate(Request $request)
    {
        $prompt = (string) $request->get('prompt', '');
        $ingredients = $request->get('ingredients');
        if (is_array($ingredients)) {
            $ingredients = implode('、', $ingredients);
        }
        $ingredients = (string) $ingredients;

        // 相同请求先查库：命中则直接返回，避免重复消耗大模型
        $generateKey = $this->buildGenerateKey($ingredients, $prompt);
        if ($generateKey !== '') {
            $cached = Recipe::with(['directions', 'ingredients', 'category'])
                ->where('ai_prompt', $generateKey)
                ->first();
            if ($cached) {
                return ApiResponse::success($cached->toArray(), '已从已有生成中获取');
            }
        }

        try {
            $recipeData = app(RecipeAiService::class)->generate([
                'ingredients' => $ingredients,
                'prompt' => $prompt,
            ]);
        } catch (\RuntimeException $e) {
            return ApiResponse::serverError('AI 生成失败：'.$e->getMessage());
        }

        // 分类：按名称精确匹配，不存在则新建顶级分类
        $category = RecipeCategory::where('name', $recipeData['category'])->first()
            ?? RecipeCategory::create([
                'name' => $recipeData['category'],
                'description' => '',
                'parent_id' => 0,
            ]);

        // AI 生成内容归属到统一的「AI 生成」系统用户（user_id 不可空）
        $aiUser = User::firstOrCreate(
            ['email' => 'ai@tianlong.recipe'],
            ['name' => 'AI 生成', 'password' => bcrypt(Str::random(32))]
        );

        // 按菜名从 Unsplash 自动配图（失败则留空，不影响主流程）
        $image = $this->fetchAndStoreImage($recipeData['name']);

        $recipe = Recipe::create([
            'name' => $recipeData['name'],
            'description' => $recipeData['description'],
            'category_id' => $category->id,
            'image' => $image ?? '',
            'user_id' => $aiUser->id,
            'ai_prompt' => $generateKey !== '' ? $generateKey : null,
        ]);

        $this->syncIngredients($recipe, $recipeData['ingredients']);
        $this->syncDirections($recipe, $recipeData['directions']);
        $this->clearRecipeCache();

        return ApiResponse::success(
            $recipe->load(['directions', 'ingredients', 'category'])->toArray(),
            'AI 生成食谱成功',
            201
        );
    }

    /**
     * 构造生成请求的缓存键（食材 + 主题），用于复用判定与落库记录。
     */
    private function buildGenerateKey(string $ingredients, string $prompt): string
    {
        $parts = [];
        if ($ingredients !== '') {
            $parts[] = '食材:'.$ingredients;
        }
        if ($prompt !== '') {
            $parts[] = '主题:'.$prompt;
        }

        return implode(' | ', $parts);
    }

    /**
     * 搜索食谱
     */
    public function search(Request $request)
    {
        $name = (string) $request->get('name', '');

        $recipes = Recipe::where('name', 'like', '%'.$name.'%')
            ->orderByDesc('id')
            ->paginate(15);

        return ApiResponse::success($recipes, '搜索食谱成功');
    }

    /**
     * 获取食谱分类
     */
    public function category(Request $request)
    {
        $parentId = (int) $request->get('parent_id', 0);
        $key = 'recipe_categories:parent:'.$parentId;

        $data = $this->remember(
            'recipe_categories',
            $key,
            fn () => RecipeCategory::where('parent_id', $parentId)->orderBy('id')->get()->toArray()
        );

        return ApiResponse::success($data, '分类信息');
    }

    /**
     * 获取食谱详情
     */
    public function info(Request $request)
    {
        $id = $request->get('id');

        if (! $id) {
            return ApiResponse::validationError('参数 id 不能为空');
        }

        $data = $this->remember('recipes', 'recipe:'.$id, function () use ($id) {
            $recipe = Recipe::with(['directions', 'ingredients', 'category'])->find($id);

            return $recipe?->toArray();
        });

        if (! $data) {
            return ApiResponse::notFound('食谱不存在');
        }

        return ApiResponse::success($data, '获取食谱详情成功');
    }

    /**
     * API 食谱列表（简单版本）
     */
    public function index()
    {
        $recipes = Recipe::orderByDesc('created_at')->paginate(15);

        return ApiResponse::success($recipes, '获取食谱列表成功');
    }

    /**
     * 获取创建表单初始数据
     */
    public function create()
    {
        return ApiResponse::success([
            'form' => Recipe::form(),
            'categories' => RecipeCategory::orderBy('id')->get(),
        ]);
    }

    /**
     * 创建新食谱
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'required|max:3000',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|max:255',
            'ingredients.*.qty' => 'required|max:255',
            'directions' => 'required|array|min:1',
            'directions.*.description' => 'required|max:3000',
            'category_id' => 'required|exists:recipe_categories,id',
        ]);

        $recipe = new Recipe([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
        ]);

        if ($request->hasFile('image')) {
            $recipe->image = $this->storeImage($request->file('image'));
        }

        $request->user()->recipes()->save($recipe);

        $this->syncIngredients($recipe, $validated['ingredients']);
        $this->syncDirections($recipe, $validated['directions']);

        $this->clearRecipeCache();

        return ApiResponse::success(['id' => $recipe->id], '食谱创建成功', 201);
    }

    /**
     * 获取食谱详情（资源路由 show）
     */
    public function show($id)
    {
        $recipe = Recipe::with(['user:id,name', 'category', 'ingredients', 'directions'])
            ->find($id);

        if (! $recipe) {
            return ApiResponse::notFound('食谱不存在');
        }

        return ApiResponse::success($recipe, '获取食谱详情成功');
    }

    /**
     * 获取编辑表单数据
     */
    public function edit($id, Request $request)
    {
        $recipe = $request->user()->recipes()
            ->with([
                'ingredients' => fn ($q) => $q->select('id', 'recipe_id', 'name', 'qty'),
                'directions' => fn ($q) => $q->select('id', 'recipe_id', 'description'),
            ])
            ->find($id, ['id', 'name', 'description', 'image', 'category_id']);

        if (! $recipe) {
            return ApiResponse::notFound('食谱不存在或无权编辑');
        }

        return ApiResponse::success([
            'form' => $recipe,
            'categories' => RecipeCategory::orderBy('id')->get(),
        ]);
    }

    /**
     * 更新食谱
     */
    public function update($id, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'required|max:3000',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.id' => 'nullable|integer|exists:recipe_ingredients,id',
            'ingredients.*.name' => 'required|max:255',
            'ingredients.*.qty' => 'required|max:255',
            'directions' => 'required|array|min:1',
            'directions.*.id' => 'nullable|integer|exists:recipe_directions,id',
            'directions.*.description' => 'required|max:3000',
            'category_id' => 'required|exists:recipe_categories,id',
        ]);

        $recipe = $request->user()->recipes()->find($id);

        if (! $recipe) {
            return ApiResponse::notFound('食谱不存在或无权修改');
        }

        $recipe->name = $validated['name'];
        $recipe->description = $validated['description'];
        $recipe->category_id = $validated['category_id'];

        if ($request->hasFile('image')) {
            $oldImage = $recipe->image;
            $recipe->image = $this->storeImage($request->file('image'));
            $this->deleteImage($oldImage);
        }

        $recipe->save();

        $this->syncIngredients($recipe, $validated['ingredients']);
        $this->syncDirections($recipe, $validated['directions']);

        $this->clearRecipeCache();

        return ApiResponse::success(['id' => $recipe->id], '食谱更新成功');
    }

    /**
     * 删除食谱
     */
    public function destroy($id, Request $request)
    {
        $recipe = $request->user()->recipes()->find($id);

        if (! $recipe) {
            return ApiResponse::notFound('食谱不存在或无权删除');
        }

        $recipe->ingredients()->delete();
        $recipe->directions()->delete();
        $this->deleteImage($recipe->image);
        $recipe->favoritedBy()->detach();

        $recipe->delete();

        $this->clearRecipeCache();

        return ApiResponse::success(null, '食谱删除成功');
    }

    /**
     * 收藏食谱
     */
    public function favorite(Request $request)
    {
        $data = $this->validateFavorite($request);

        $request->user()->favoriteRecipes()->syncWithoutDetaching([$data['recipe_id']]);

        return ApiResponse::success(null, '食谱已收藏');
    }

    /**
     * 取消收藏食谱
     */
    public function unfavorite(Request $request)
    {
        $data = $this->validateFavorite($request);

        $request->user()->favoriteRecipes()->detach($data['recipe_id']);

        return ApiResponse::success(null, '已取消收藏');
    }

    /**
     * 判断是否已收藏
     */
    public function isFavorite(Request $request)
    {
        $data = $this->validateFavorite($request);

        $isFavorite = $request->user()->favoriteRecipes()
            ->whereKey($data['recipe_id'])
            ->exists();

        return ApiResponse::success(
            ['isFavorite' => $isFavorite],
            $isFavorite ? '已收藏' : '未收藏'
        );
    }

    /**
     * 获取用户收藏列表
     */
    public function favoriteList(Request $request)
    {
        $recipes = $request->user()->favoriteRecipes()
            ->orderByPivot('created_at', 'desc')
            ->paginate(15);

        return ApiResponse::success($recipes, '获取收藏列表成功');
    }

    /**
     * @return array{recipe_id: int}
     */
    private function validateFavorite(Request $request): array
    {
        return $request->validate([
            'recipe_id' => 'required|integer|exists:recipes,id',
        ]);
    }

    /**
     * 按关键词自动配图并保存到本地 images 磁盘（可选 COS）。
     * 优先 Bing 搜索（中文菜名更准），无候选时回退 Pexels/Unsplash，
     * 再失败退回本地图（库内同名菜谱图 / placeholder.jpg）。
     */
    private function fetchAndStoreImage(string $query): ?string
    {
        try {
            // 优先用 Bing 搜图（中文菜名更准），未拿到候选再回退 Pexels/Unsplash
            $candidates = app(BingImageService::class)->search($query, 8);

            if (empty($candidates)) {
                $candidates = array_merge(
                    $this->searchPexelsImages($query),
                    $this->searchUnsplashImages($query)
                );
            }

            $contents = $candidates ? $this->downloadFirstImage($candidates) : null;

            if ($contents !== null) {
                $filename = Str::random(32).'.jpg';
                Storage::disk('images')->put($filename, $contents);

                if ($this->cosConfigured()) {
                    app(CosService::class)->upload($filename, $contents);
                }

                return $filename;
            }
        } catch (\Throwable $e) {
            Log::warning('[recipe.image] 自动配图失败：'.$e->getMessage());
        }

        // 外部图源不可用（未配 key / 网络不通 / 下载失败）时退回本地图，保证前端有图
        return $this->pickLocalImage($query);
    }

    /**
     * 外部图源全部失败时的本地兜底：
     * 先找库内同名/同关键词菜谱已有的图，再退回固定占位图。
     */
    private function pickLocalImage(string $query): ?string
    {
        if (! config('services.image.local_fallback', true)) {
            return null;
        }

        $keywords = array_filter(
            preg_split('/[\s,，、\/+和与]+/u', $query) ?: [],
            fn ($k) => mb_strlen(trim($k)) >= 2
        );
        $keywords[] = trim($query);

        foreach ($keywords as $keyword) {
            $hit = Recipe::where('image', '!=', '')
                ->where('name', 'like', '%'.trim($keyword).'%')
                ->inRandomOrder()
                ->value('image');

            if ($hit) {
                return $hit;
            }
        }

        return is_file(public_path('images/placeholder.jpg')) ? 'placeholder.jpg' : null;
    }

    /**
     * Unsplash 搜索：返回若干候选图片地址（大图优先）。
     * api.unsplash.com 偶发抖动，带一次重试。
     */
    private function searchUnsplashImages(string $query): array
    {
        $accessKey = (string) config('services.unsplash.access_key');

        if ($accessKey === '' || Cache::get('img_source_down:unsplash')) {
            return [];
        }

        try {
            $search = Http::timeout((int) config('services.unsplash.timeout', 15))
                ->retry(2, 500, throw: false)
                ->withHeader('Authorization', 'Client-ID '.$accessKey)
                ->get('https://api.unsplash.com/search/photos', [
                    'query' => $query.' 美食',
                    'per_page' => 3,
                    'orientation' => 'landscape',
                    'content_filter' => 'high',
                ]);
        } catch (\Throwable $e) {
            $this->markSourceDown('unsplash');

            return [];
        }

        if ($search->failed()) {
            if ($search->status() !== 401) {
                $this->markSourceDown('unsplash');
            }

            return [];
        }

        $urls = [];
        foreach ((array) $search->json('results', []) as $item) {
            foreach (['regular', 'small'] as $size) {
                if (! empty($item['urls'][$size])) {
                    $urls[] = $item['urls'][$size];
                }
            }
        }

        return $urls;
    }

    /**
     * Pexels 搜索（可选）：未配置 PEXELS_API_KEY 时直接跳过。
     */
    private function searchPexelsImages(string $query): array
    {
        $apiKey = (string) config('services.pexels.api_key');

        if ($apiKey === '' || Cache::get('img_source_down:pexels')) {
            return [];
        }

        try {
            $search = Http::timeout((int) config('services.pexels.timeout', 20))
                ->retry(2, 500, throw: false)
                ->withHeaders(['Authorization' => $apiKey])
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'per_page' => 3,
                    'orientation' => 'landscape',
                ]);
        } catch (\Throwable $e) {
            $this->markSourceDown('pexels');

            return [];
        }

        if ($search->failed()) {
            if ($search->status() !== 401) {
                $this->markSourceDown('pexels');
            }

            return [];
        }

        $urls = [];
        foreach ((array) $search->json('photos', []) as $photo) {
            if (! empty($photo['src']['large2x'])) {
                $urls[] = $photo['src']['large2x'];
            } elseif (! empty($photo['src']['large'])) {
                $urls[] = $photo['src']['large'];
            }
        }

        return $urls;
    }

    /**
     * 标记某图源暂时不可用（10 分钟内跳过，避免每次生成都白等超时）。
     */
    private function markSourceDown(string $source): void
    {
        Cache::put('img_source_down:'.$source, true, now()->addMinutes(10));
    }

    /**
     * 依次尝试下载候选图片：先直连原地址，失败则走配置的图片代理（应对 CDN 不可达）。
     * 命中即返回二进制内容；全部失败返回 null。
     */
    private function downloadFirstImage(array $urls): ?string
    {
        $proxyTemplate = (string) config('services.unsplash.image_proxy', '');
        $attempts = 0;

        foreach (array_slice(array_unique($urls), 0, 4) as $url) {
            $targets = [$url];

            if ($proxyTemplate !== '' && str_contains($proxyTemplate, '{url}')) {
                $targets[] = str_replace('{url}', rawurlencode($url), $proxyTemplate);
            }

            foreach ($targets as $target) {
                // 控制总尝试次数，避免接口被拖太久
                if ($attempts++ >= 4) {
                    return null;
                }

                try {
                    $response = Http::timeout(25)->get($target);
                } catch (\Throwable $e) {
                    continue;
                }

                $body = $response->body();

                if ($response->failed() || strlen($body) < 5000) {
                    continue;
                }

                if (! $this->looksLikeImage($response->header('Content-Type'), $body)) {
                    continue;
                }

                return $body;
            }
        }

        return null;
    }

    /**
     * 粗略校验下载内容是图片而非错误页/HTML。
     */
    private function looksLikeImage(string $contentType, string $body): bool
    {
        if (str_starts_with(strtolower($contentType), 'image/')) {
            return true;
        }

        return str_starts_with($body, "\xFF\xD8\xFF")   // JPEG
            || str_starts_with($body, "\x89PNG")        // PNG
            || str_starts_with($body, 'GIF8')           // GIF
            || (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 16), 'WEBP'));
    }

    /**
     * 保存上传图片：始终写入本地 images 磁盘，配置了 COS 时同时上传。
     */
    private function storeImage(UploadedFile $file): string
    {
        $filename = Str::random(32).'.'.$file->extension();

        Storage::disk('images')->putFileAs('', $file, $filename);

        if ($this->cosConfigured()) {
            app(CosService::class)->upload($filename, file_get_contents($file->getRealPath()));
        }

        return $filename;
    }

    private function deleteImage(?string $filename): void
    {
        if (empty($filename)) {
            return;
        }

        Storage::disk('images')->delete($filename);

        if ($this->cosConfigured()) {
            app(CosService::class)->delete($filename);
        }
    }

    private function cosConfigured(): bool
    {
        return app(CosService::class)->isConfigured();
    }

    /**
     * 同步配料：新增 / 更新 / 删除本次未提交的旧数据。
     */
    private function syncIngredients(Recipe $recipe, array $items): void
    {
        $kept = [];

        foreach ($items as $item) {
            if (! empty($item['id'])) {
                $ingredient = $recipe->ingredients()->find($item['id']);

                if ($ingredient) {
                    $ingredient->update(['name' => $item['name'], 'qty' => $item['qty']]);
                    $kept[] = $ingredient->id;
                }
            } else {
                $kept[] = $recipe->ingredients()->create([
                    'name' => $item['name'],
                    'qty' => $item['qty'],
                ])->id;
            }
        }

        $recipe->ingredients()->whereNotIn('id', $kept)->delete();
    }

    /**
     * 同步制作步骤：新增 / 更新 / 删除本次未提交的旧数据。
     */
    private function syncDirections(Recipe $recipe, array $items): void
    {
        $kept = [];

        foreach ($items as $item) {
            if (! empty($item['id'])) {
                $direction = $recipe->directions()->find($item['id']);

                if ($direction) {
                    $direction->update(['description' => $item['description']]);
                    $kept[] = $direction->id;
                }
            } else {
                $kept[] = $recipe->directions()->create([
                    'description' => $item['description'],
                ])->id;
            }
        }

        $recipe->directions()->whereNotIn('id', $kept)->delete();
    }

    /**
     * 带标签的缓存读取，缓存不可用时直接回源。
     */
    private function remember(string $tag, string $key, callable $callback)
    {
        try {
            return Cache::tags([$tag])->remember($key, self::CACHE_TTL, $callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }

    /**
     * 清除食谱相关缓存。
     */
    private function clearRecipeCache(): void
    {
        try {
            Cache::tags(['recipes'])->flush();
        } catch (\Throwable $e) {
            // 缓存不可用时无需处理
        }
    }
}
