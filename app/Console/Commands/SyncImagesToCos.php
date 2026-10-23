<?php

namespace App\Console\Commands;

use App\Services\CosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 把本地菜谱图片同步到腾讯云 COS。
 *
 * 背景：Recipe::image_src 的逻辑是「本地 public/images 有该文件 -> 返回 /images/xxx，
 * 否则走 CosService 返回 CDN 地址」。而生产站点走 COS，如果没有把图片传上去，
 * 接口就会返回 CDN 地址但实际 404。
 *
 * 用法：
 *   php artisan cos:sync-images --dry-run        # 先看要传多少
 *   php artisan cos:sync-images                  # 只传 COS 上缺的
 *   php artisan cos:sync-images --force          # 全部覆盖
 *   php artisan cos:sync-images --only-used      # 只传 recipes_full.json 引用到的
 */
class SyncImagesToCos extends Command
{
    protected $signature = 'cos:sync-images
                            {--only-used : 只同步 recipes_full.json 引用到的图片}
                            {--force : 不检查远端是否存在，全部覆盖上传}
                            {--dry-run : 只统计不实际上传}
                            {--limit=0 : 最多上传多少个（0 = 不限）}';

    protected $description = '把本地 public/images 的菜谱图片同步到腾讯云 COS';

    public function handle(CosService $cos): int
    {
        if (! $cos->isConfigured()) {
            $this->error('COS 未配置，检查 .env 里的 COS_SECRET_ID / COS_SECRET_KEY / COS_BUCKET');
            return self::FAILURE;
        }

        $dir = public_path('images');
        if (! is_dir($dir)) {
            $this->error("图片目录不存在: {$dir}");
            return self::FAILURE;
        }

        $local = [];
        foreach (File::files($dir) as $f) {
            $ext = strtolower($f->getExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $local[$f->getFilename()] = $f->getPathname();
            }
        }
        $this->info('本地图片: '.count($local).' 张');

        // 只同步数据里实际用到的
        if ($this->option('only-used')) {
            $jsonPath = base_path('import/recipes_full.json');
            if (! File::exists($jsonPath)) {
                $this->error("找不到 {$jsonPath}");
                return self::FAILURE;
            }
            $rows = json_decode(File::get($jsonPath), true) ?: [];
            $used = [];
            foreach ($rows as $r) {
                $img = trim((string) ($r['image'] ?? ''));
                if ($img !== '') {
                    $used[$img] = true;
                }
            }
            $local = array_intersect_key($local, $used);
            $this->info('按 JSON 过滤后: '.count($local).' 张');
        }

        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        if ($dry) {
            $this->warn('【dry-run】只统计，不上传');
        }

        $bar = $this->output->createProgressBar(count($local));
        $bar->start();

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $failList = [];

        foreach ($local as $name => $path) {
            if ($limit > 0 && $uploaded >= $limit) {
                break;
            }

            try {
                // 判断远端是否已存在（--force 时跳过检查，一律覆盖）
                $exists = false;
                if (! $force) {
                    try {
                        $exists = $cos->exists($name);
                    } catch (\Throwable $e) {
                        $exists = false;
                    }
                }

                if ($exists) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // dry-run 只统计「将要上传」的数量
                if ($dry) {
                    $uploaded++;
                    $bar->advance();
                    continue;
                }

                $cos->uploadFile($name, $path);
                $uploaded++;
            } catch (\Throwable $e) {
                $failed++;
                $failList[] = $name.' => '.$e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('========== 同步结果 ==========');
        $this->info("已上传: {$uploaded}");
        $this->info('跳过(已存在): '.$skipped);
        $this->info("失败: {$failed}");
        $this->info('CDN 前缀: '.config('filesystems.disks.cos.cdn'));

        if ($failList !== []) {
            $this->warn('部分失败（前 10 条）:');
            foreach (array_slice($failList, 0, 10) as $line) {
                $this->warn('  '.$line);
            }
        }

        if ($dry) {
            $this->newLine();
            $this->warn('这是 dry-run，没有真正上传。去掉 --dry-run 再执行。');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
