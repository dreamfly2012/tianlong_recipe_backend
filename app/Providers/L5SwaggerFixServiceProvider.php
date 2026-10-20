<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class L5SwaggerFixServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 在Laravel 12中，这些trait已经被移到了不同的命名空间
        // 我们不需要在这里做任何特殊处理，让L5-Swagger自行处理
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}