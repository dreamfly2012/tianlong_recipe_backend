<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'wechat_miniprogram' => [
        'appid' => env('WECHAT_MINIPROGRAM_APPID'),
        'secret' => env('WECHAT_MINIPROGRAM_SECRET'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // OpenAI 兼容的大模型网关，用于 AI 生成菜谱（recipe.generate）
    'ai' => [
        'base_uri' => env('AI_BASE_URI'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('AI_TIMEOUT', 60),
        'json_mode' => env('AI_JSON_MODE', true),
    ],

    // Unsplash 图库，用于 AI 生成菜谱时按菜名自动配图（可选，未配置则无图）
    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
        'timeout' => env('UNSPLASH_TIMEOUT', 15),
        // 图片下载兜底代理：images.unsplash.com 在部分网络（国内）不可达，
        // {url} 会被替换为原图地址的 urlencode 值，经代理转存。
        'image_proxy' => env('IMAGE_PROXY_BASE', 'https://wsrv.nl/?url={url}&w=1200&output=jpg'),
    ],

    // Pexels 作为 Unsplash 搜索失败时的备用图源（其 CDN images.pexels.com 国内可直连）
    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
        'timeout' => env('PEXELS_TIMEOUT', 20),
    ],

    // 配图全局开关：外部图源都不可用时，是否退回本地图（库内同名菜谱图 / placeholder.jpg）
    'image' => [
        'local_fallback' => env('IMAGE_LOCAL_FALLBACK', true),
    ],

];
