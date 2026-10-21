<?php

namespace Tests\Unit;

use App\Services\BingImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BingImageService 测试，分两层：
 *
 * 1) 离线回归（默认 `php artisan test` 即跑，不需要网络）
 *    - 用真实的 Bing 搜索结果 HTML 作为 fixture，通过 Http::fake 喂给服务，
 *      断言「给定这份已知格式的 HTML，解析逻辑稳定提取出 N 条原图地址」。
 *    - 作用：防止我们自己改坏解析正则（回归保护）。
 *
 * 2) 在线哨兵（需要网络，`php artisan test --group online` 才跑，
 *    建议接到 CI 定时任务 / 每日手动跑一次）
 *    - 真实请求 cn.bing.com，断言当前线上仍能提取出图片地址。
 *    - 作用：Bing 一旦改版、反爬或换 DOM 结构导致抓不到图，测试最先报警。
 *
 * fixture 位置：tests/fixtures/bing/
 * 刷新线上基准：BING_ONLINE_TEST=1 BING_REFRESH_FIXTURE=1 php artisan test --group online
 */
class BingImageServiceTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = __DIR__ . '/../fixtures/bing';
        // 测试环境用 array 缓存，清空避免前一次网络失败留下的 down 标记污染
        Cache::flush();
        Config::set('services.bing.enabled', true);
    }

    private function fixture(string $name): string
    {
        $path = $this->fixtureDir . '/' . $name;
        if (! is_file($path)) {
            $this->fail("缺少 fixture 文件: {$path}");
        }

        return file_get_contents($path);
    }

    /** 用 fixture 拦截两个 Bing 端点。async 默认返回空，模拟「静态页已足够」的情形。 */
    private function fakeBing(string $staticBody, string $asyncBody = ''): void
    {
        Http::fake([
            'https://cn.bing.com/images/search*' => Http::response($staticBody, 200),
            'https://cn.bing.com/images/async*'  => Http::response($asyncBody, 200),
        ]);
    }

    /**
     * 核心回归断言：给定真实静态页 HTML，必须稳定提取出全部有效原图。
     * 基准值 35 来自首次抓取（去重后）。Bing 改结构 / 正则回退都会让它变少 → 立刻红。
     */
    public function test_static_page_extracts_baseline_count(): void
    {
        $this->fakeBing($this->fixture('bing_static_gongbaojiding.html'));

        $urls = app(BingImageService::class)->search('宫保鸡丁', 100);

        $this->assertNotEmpty($urls, '静态页应至少提取到 1 条图片地址');
        $this->assertCount(35, $urls, '解析供给量回退到基准 35 条，说明提取逻辑或 fixture 变了');
        $this->assertSame(array_values(array_unique($urls)), $urls, '返回结果应已去重');

        foreach ($urls as $url) {
            $this->assertMatchesRegularExpression('#^https?://#i', $url, "提取到非法地址: {$url}");
        }
    }

    /**
     * limit 必须真正生效，且返回的条数不超过 limit。
     */
    public function test_limit_is_respected(): void
    {
        $this->fakeBing($this->fixture('bing_static_gongbaojiding.html'));

        $urls = app(BingImageService::class)->search('宫保鸡丁', 5);

        $this->assertCount(5, $urls);
    }

    /**
     * 静态页不足 limit 时，应回退到异步瓦片端点补充候选。
     */
    public function test_falls_back_to_async_when_static_thin(): void
    {
        // 静态页只给 2 条，async 给完整 fixture
        $thin = '{"mock":1} <div class="img_card" data-m="{&quot;murl&quot;:&quot;https://example.com/a.jpg&quot;}"></div>';
        $this->fakeBing($thin, $this->fixture('bing_async_gongbaojiding.html'));

        $urls = app(BingImageService::class)->search('宫保鸡丁', 100);

        // async fixture 去重后 35 条，加上静态页 1 条（不同域名）→ 应 > 30
        $this->assertGreaterThanOrEqual(30, count($urls), 'async 回退应贡献大量候选地址');
    }

    /**
     * 三种提取模式（还原实体后的 murl、实体兜底 murl、imgurl=）都必须可用。
     * 用合成 HTML 覆盖，确保将来改动某一模式时不至于悄悄失效。
     */
    public function test_all_three_extraction_modes_work(): void
    {
        $html = implode("\n", [
            // 模式 1：标准 JSON 内联（&quot; 还原后变成 "）
            '<div class="img_card" data-m="{&quot;murl&quot;:&quot;https://a.com/1.jpg&quot;}"></div>',
            // 模式 2：实体兜底（即便还原不完整也应命中）
            '<span murl&quot;:&quot;https://b.com/2.jpg></span>',
            // 模式 3：imgurl= 直链参数
            '<a href="https://cn.bing.com/img?imgurl=https%3A%2F%2Fc.com%2F3.jpg&id=1">x</a>',
        ]);
        $this->fakeBing($html);

        $urls = app(BingImageService::class)->search('测试', 100);

        $this->assertContains('https://a.com/1.jpg', $urls);
        $this->assertContains('https://b.com/2.jpg', $urls);
        $this->assertContains('https://c.com/3.jpg', $urls);
    }

    /**
     * 空关键词不应触发请求，直接返回空数组。
     */
    public function test_empty_keyword_returns_empty_without_request(): void
    {
        $service = app(BingImageService::class);

        $this->assertSame([], $service->search('   '));
        $this->assertSame([], $service->search(''));
        // 确认没有发出任何 HTTP 请求
        Http::assertNothingSent();
    }

    /**
     * services.bing.enabled=false 时应直接返回空，不请求。
     */
    public function test_disabled_returns_empty_without_request(): void
    {
        Config::set('services.bing.enabled', false);

        $this->assertSame([], app(BingImageService::class)->search('宫保鸡丁'));
        Http::assertNothingSent();
    }

    /**
     * Bing 返回非 2xx（如被墙时的连接超时/异常）时，应标记 10 分钟降级，
     * 避免每次生成菜谱都白等一次超时。
     * 注意：只有「网络层异常/超时」才会写降级标记（被 catch 捕获）；
     * 正常的 HTTP 错误响应（如 503）走 failed() 分支，直接返回空且不标记。
     */
    public function test_network_exception_marks_cooldown_and_returns_empty(): void
    {
        // 模拟连接超时 / 网络不可达：Http 请求直接抛异常
        Http::fake(function () {
            throw new \Exception('模拟网络不可达 / 超时（如被墙）');
        });

        $urls = app(BingImageService::class)->search('宫保鸡丁', 5);

        $this->assertSame([], $urls);
        // 降级标记写入缓存（array store 在测试中可见）
        $this->assertTrue(Cache::has('img_source_down:bing'));
    }

    /**
     * 正常的 HTTP 错误响应（如 503 限流）应返回空，但不写降级标记，
     * 因为这类响应通常短暂，不应长时间禁用 Bing。
     */
    public function test_http_error_response_returns_empty_without_cooldown(): void
    {
        Http::fake([
            'https://cn.bing.com/images/search*' => Http::response('', 503),
            'https://cn.bing.com/images/async*'  => Http::response('', 503),
        ]);

        $urls = app(BingImageService::class)->search('宫保鸡丁', 5);

        $this->assertSame([], $urls);
        $this->assertFalse(Cache::has('img_source_down:bing'));
    }

    /**
     * @group online
     * 在线哨兵：真实访问 cn.bing.com，验证当前线上仍能提取图片。
     * 默认跳过（需显式开启），建议 CI 定时 / 每日手动跑。
     */
    public function test_live_bing_returns_images(): void
    {
        if (! env('BING_ONLINE_TEST')) {
            $this->markTestSkipped('设置 BING_ONLINE_TEST=1 才运行在线探测（需访问 cn.bing.com）。');
        }

        // 清空可能残留的 Http::fake 规则，确保真实请求不被拦截
        Http::fake();

        $service = app(BingImageService::class);
        $urls = $service->search('宫保鸡丁', 100);

        // 宽松下限：线上偶发波动不应误报，但「基本抓不到图」必须报警
        $this->assertGreaterThanOrEqual(
            5,
            count($urls),
            '线上 Bing 提取到的图片少于 5 条，可能已被反爬/改版，请人工核查。'
        );

        fwrite(STDERR, sprintf("\n[bing-live] 提取到 %d 条图片地址\n", count($urls)));

        // 可选：把线上响应刷新回 fixture，作为新的离线基准
        if (env('BING_REFRESH_FIXTURE')) {
            // 重新真实抓取并覆盖 fixture
            $html = (string) Http::withHeaders([
                'Accept-Language' => 'zh-CN,zh;q=0.9',
                'Referer' => 'https://cn.bing.com/',
            ])->withUserAgent('Mozilla/5.0')->get('https://cn.bing.com/images/search', [
                'q' => '宫保鸡丁', 'form' => 'HDRSC2', 'first' => 1, 'count' => 35,
            ])->body();

            if ($html !== '') {
                file_put_contents($this->fixtureDir . '/bing_static_gongbaojiding.html', $html);
                fwrite(STDERR, "\n[bing-live] 已刷新 fixture\n");
            }
        }
    }
}
