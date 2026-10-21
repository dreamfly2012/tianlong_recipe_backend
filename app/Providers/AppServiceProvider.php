<?php

namespace App\Providers;

use App\Services\CosService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 自封装的 COS 存储服务（取代 overtrue/laravel-filesystem-cos 的 provider）。
        $this->app->singleton(CosService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep string columns within InnoDB's index key-length limit on MySQL
        // setups that cap at 1000 bytes (utf8mb4). Without this, unique indexes
        // on varchar(255) columns (e.g. users.email) fail to migrate.
        Schema::defaultStringLength(191);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
