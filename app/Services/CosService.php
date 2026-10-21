<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Overtrue\Flysystem\Cos\CosAdapter;

/**
 * 自封装的腾讯云 COS 存储服务。
 *
 * 不再依赖 overtrue/laravel-filesystem-cos：该包在 Laravel 12 / Flysystem 3
 * 下注册的 Storage 实例返回的是原生 League\Flysystem\Filesystem，缺少
 * Laravel 的 FilesystemAdapter 代理层，导致 Storage::disk('cos')->put()
 * 报「undefined method」。这里改为直接用底层已安装的 overtrue/flysystem-cos
 * 的 CosAdapter 自建 FilesystemAdapter，逻辑完全可控，且不再受
 * composer update 冲刷 vendor 补丁的影响。
 */
class CosService
{
    protected FilesystemAdapter $disk;

    public function __construct()
    {
        $config = config('filesystems.disks.cos', []);

        $adapter = new CosAdapter($config);

        $this->disk = new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
    }

    /**
     * 是否配置了完整的 COS 凭据（决定是否真正上传到 COS）。
     */
    public function isConfigured(): bool
    {
        $disk = config('filesystems.disks.cos', []);

        return ! empty($disk['secret_id']) && ! empty($disk['secret_key']) && ! empty($disk['bucket']);
    }

    /**
     * 上传字符串内容到 COS。
     */
    public function upload(string $key, $contents): void
    {
        $this->disk->put(ltrim($key, '/'), $contents);
    }

    /**
     * 上传本地文件（资源路径）到 COS。
     */
    public function uploadFile(string $key, string $realPath): void
    {
        $this->disk->put(ltrim($key, '/'), fopen($realPath, 'rb'));
    }

    /**
     * 从 COS 删除对象（不存在时静默跳过）。
     */
    public function delete(string $key): void
    {
        $key = ltrim($key, '/');

        if ($this->disk->exists($key)) {
            $this->disk->delete($key);
        }
    }

    /**
     * 返回对象访问 URL：
     *  - 配置了 CDN 时返回 CDN URL（与模型 getImageSrcAttribute 契约一致）
     *  - 否则返回本地 /images/... URL（开发 / 本地回退）
     */
    public function url(string $key): string
    {
        $cdn = config('filesystems.disks.cos.cdn');

        if (! empty($cdn)) {
            return rtrim((string) $cdn, '/').'/'.ltrim($key, '/');
        }

        return '/images/'.ltrim($key, '/');
    }
}
