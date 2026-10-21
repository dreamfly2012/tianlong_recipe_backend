<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * 通过 cn.bing.com 图片搜索按关键词获取菜谱配图候选地址（无需 API key）。
 *
 * 背景：AI 生成菜谱后用菜名自动配图，原先走 Pexels/Unsplash 对中文菜名命中不准；
 * Bing 的中文图源更贴切。Bing 把图片元数据内联进 HTML，且双引号被 HTML 实体化
 * 为 &quot;，因此提取时先还原关键实体再做正则匹配最稳。
 *
 * 本服务只负责「搜图并返回候选原图地址数组」，真正的下载 / 校验 / 防盗链代理 /
 * 本地兜底由 RecipeController 复用既有 downloadFirstImage 等方法处理。
 */
class BingImageService
{
    private const BASE = 'https://cn.bing.com/images/search';
    private const ASYNC = 'https://cn.bing.com/images/async';
    private const CACHE_DOWN_KEY = 'img_source_down:bing';

    /**
     * 按关键词搜索图片，返回去重后的原图地址数组（最多 $limit 条）。
     *
     * @return string[]
     */
    public function search(string $keyword, int $limit = 8): array
    {
        if (! (bool) config('services.bing.enabled', true)) {
            return [];
        }

        if (Cache::get(self::CACHE_DOWN_KEY)) {
            return [];
        }

        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $headers = [
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'Referer' => 'https://cn.bing.com/',
        ];

        $urls = $this->extractFrom($this->fetch(self::BASE, $keyword, $headers));

        // 静态页不足时补一次异步瓦片端点（Bing 图片结果常从此返回）
        if (count($urls) < $limit) {
            $urls = array_merge(
                $urls,
                $this->extractFrom($this->fetch(self::ASYNC, $keyword, $headers))
            );
        }

        $urls = array_values(array_unique($urls));

        return array_slice($urls, 0, $limit);
    }

    private function fetch(string $endpoint, string $keyword, array $headers): string
    {
        try {
            $response = Http::timeout((int) config('services.bing.timeout', 20))
                ->withHeaders($headers)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')
                ->get($endpoint, [
                    'q' => $keyword,
                    'form' => 'HDRSC2',
                    'first' => 1,
                    'count' => 35,
                    'mmasync' => 1,
                ]);

            if ($response->failed()) {
                return '';
            }

            return $response->body();
        } catch (\Throwable $e) {
            // 网络不通 / 超时：标记 10 分钟内跳过，避免每次生成都白等
            Cache::put(self::CACHE_DOWN_KEY, true, now()->addMinutes(10));

            return '';
        }
    }

    /**
     * 从 Bing 返回的 HTML 中提取原图地址。
     * Bing 把数据内联且把 JSON 双引号实体化为 &quot;，所以先还原关键实体，
     * 再用 murl":" 提取；并附带实体感知正则与 imgurl= 参数两种兜底。
     *
     * @return string[]
     */
    private function extractFrom(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $work = str_replace(['&quot;', '&amp;'], ['"', '&'], $html);

        $urls = [];

        // 模式 1：还原实体后的 murl":"https://...
        if (preg_match_all('/murl":"(https?:\/\/[^"]+)"/', $work, $m)) {
            foreach ($m[1] as $u) {
                $this->push($urls, $u);
            }
        }

        // 模式 2：实体兜底（以防还原不完整）
        if (preg_match_all('/murl&quot;:&quot;([^"&<>\s]{5,300})/', $html, $m2)) {
            foreach ($m2[1] as $u) {
                $this->push($urls, $u);
            }
        }

        // 模式 3：imgurl= 直链参数（详情链接）
        if (preg_match_all('/imgurl=([^&\'"]+)/', $html, $m3)) {
            foreach ($m3[1] as $u) {
                $this->push($urls, urldecode($u));
            }
        }

        return $urls;
    }

    private function push(array &$urls, string $url): void
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return;
        }
        if (! in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }
}
