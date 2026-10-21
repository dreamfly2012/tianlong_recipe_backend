<?php

namespace Tests\Unit;

use App\Models\Recipe;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Recipe::getImageSrcAttribute 单元测试：该访问器是前后端图片 URL 的统一出口，
 * 逻辑分支必须稳定：
 *  - 空 image → null
 *  - public/images 下存在同名文件 → 优先走本地 /images URL（避免 COS 缺文件 404）
 *  - 本地不存在 + 配置了 COS CDN → 返回 CDN URL
 *  - 本地不存在 + 未配 CDN → 回退本地 /images URL
 *
 * 不依赖数据库；仅在 public/images 下创建/删除临时文件。
 */
class RecipeImageSrcTest extends TestCase
{
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function writeLocalImage(string $name): string
    {
        $dir = public_path('images');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/'.$name;
        file_put_contents($path, 'fake-image-bytes');
        $this->createdFiles[] = $path;

        return $name;
    }

    private function configureCos(string $cdn = null): void
    {
        Config::set('filesystems.disks.cos', [
            'secret_id' => 'test-secret-id',
            'secret_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'ap-guangzhou',
            'cdn' => $cdn,
        ]);
    }

    public function test_returns_null_when_image_empty(): void
    {
        $recipe = new Recipe(['image' => '']);

        $this->assertNull($recipe->image_src);
    }

    public function test_prefers_local_file_when_present(): void
    {
        $name = $this->writeLocalImage('local_priority_'.uniqid().'.jpg');

        $recipe = new Recipe(['image' => $name]);

        $this->assertSame('/images/'.$name, $recipe->image_src);
    }

    public function test_returns_cos_cdn_url_when_configured(): void
    {
        $this->configureCos('https://cdn.example.com/');

        // 故意使用一个本地不存在的文件名，确保走 COS 分支
        $name = 'cos_only_'.uniqid().'.jpg';
        $this->assertFalse(file_exists(public_path('images/'.$name)));

        $recipe = new Recipe(['image' => $name]);

        $this->assertSame('https://cdn.example.com/'.$name, $recipe->image_src);
    }

    public function test_falls_back_to_local_url_without_cdn(): void
    {
        $this->configureCos(null);

        $name = 'no_cdn_'.uniqid().'.jpg';
        $this->assertFalse(file_exists(public_path('images/'.$name)));

        $recipe = new Recipe(['image' => $name]);

        $this->assertSame('/images/'.$name, $recipe->image_src);
    }
}
