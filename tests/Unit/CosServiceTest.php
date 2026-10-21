<?php

namespace Tests\Unit;

use App\Services\CosService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * CosService 单元测试：聚焦两个纯逻辑点（不需要真实腾讯云网络）。
 *  - isConfigured()：凭据是否齐全
 *  - url()：CDN 优先、否则回退本地 /images URL（与 Recipe::image_src 契约一致）
 *
 * 注：CosService 构造时会 new CosAdapter(config)，测试环境与 AGENTS 文档中的
 * 既有用例（test_recipe_image_src_uses_cos_cdn_when_configured）已证明传入
 * secret_id/key/bucket 时空配置不会抛异常，这里复用相同的最小配置集。
 */
class CosServiceTest extends TestCase
{
    private function configureCos(array $extra = []): void
    {
        Config::set('filesystems.disks.cos', array_merge([
            'secret_id' => 'test-secret-id',
            'secret_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'ap-guangzhou',
            'cdn' => null,
        ], $extra));
    }

    public function test_is_not_configured_without_credentials(): void
    {
        Config::set('filesystems.disks.cos', []);

        $this->assertFalse((new CosService())->isConfigured());
    }

    public function test_is_not_configured_when_only_partial_credentials(): void
    {
        Config::set('filesystems.disks.cos', [
            'secret_id' => 'test-secret-id',
            'secret_key' => '', // 缺 key
            'bucket' => 'test-bucket',
        ]);

        $this->assertFalse((new CosService())->isConfigured());
    }

    public function test_is_configured_with_full_credentials(): void
    {
        $this->configureCos();

        $this->assertTrue((new CosService())->isConfigured());
    }

    public function test_url_returns_cdn_url_when_configured(): void
    {
        $this->configureCos(['cdn' => 'https://cdn.example.com/']);

        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            (new CosService())->url('a.jpg')
        );
    }

    public function test_url_strips_leading_slash(): void
    {
        $this->configureCos(['cdn' => 'https://cdn.example.com/']);

        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            (new CosService())->url('/a.jpg')
        );
    }

    public function test_url_falls_back_to_local_without_cdn(): void
    {
        $this->configureCos(['cdn' => null]);

        $this->assertSame('/images/a.jpg', (new CosService())->url('a.jpg'));
        $this->assertSame('/images/a.jpg', (new CosService())->url('/a.jpg'));
    }
}
