# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Tianlong Recipe Backend — a Laravel 12 + Vue 3 SPA for a recipe management app (天龙食谱). API-first architecture serving a Vue 3/TypeScript frontend. Supports standard email login, registration, logout, and WeChat Mini Program login.

## Common Commands

**Development:**
```bash
composer install          # Install PHP dependencies
npm install               # Install Node dependencies
npm run dev               # Start Vite dev server (port 5173)
composer dev              # Full stack: php artisan serve + queue + logs + vite concurrently
```

**Docker:**
```bash
docker-compose up         # Start all services (nginx, php, node, mysql, redis)
```

**Testing:**
```bash
composer test             # Run all PHPUnit tests
composer test -- --filter=ClassName   # Run a specific test class
composer test -- --filter=testMethodName  # Run a single test method
```

**Lint & Format:**
```bash
npm run lint              # ESLint with auto-fix
npm run format            # Prettier format resources/
npm run format:check      # Prettier check only
npm run build             # Production Vite build
```

## Architecture

**Backend (Laravel 12):**
- `routes/api.php` — all API endpoints (recipe, auth). Recipe read endpoints are public; write endpoints require Sanctum Bearer token auth.
- `routes/web.php` — SPA fallback route (`/{any}` → `welcome` view), plus `/login` route
- `app/Models/` — `Recipe`, `User`, `RecipeCategory`, `RecipeIngredient`, `RecipeDirection`. Recipe has many ingredients and directions (1:N), and many-to-many favorites via `recipe_user` pivot table.
- `app/Http/Controllers/RecipeController.php` — central controller. Public endpoints: `lists`, `info`, `category`, `search`, `daily`, `ai`, `personalized`. Auth-protected: `store`, `update`, `destroy`, `favorite`, `unfavorite`, `isFavorite`, `favoriteList`.
- `app/Http/Controllers/AuthController.php` — handles `login`, `register`, `logout`, and `wechatLogin` (mini-program code exchange + auto-register).
- `app/Http/Helpers/ApiResponse.php` — shared response helper with constants (`CODE_SUCCESS=0`, `CODE_ERROR=1`, etc.) and static methods `success()`, `error()`, `validationError()`, `notFound()`, `unauthorized()`, `forbidden()`, `serverError()`.
- Authentication: Laravel Sanctum (`HasApiTokens`). Route middleware `auth:api` is applied in `RecipeController::__construct()`.

**Frontend (Vue 3 + Vite + TypeScript):**
- Source: `resources/assets/js/` — views in `views/`, components in `components/`, router/store in `router/` and `stores/`, API helpers in `helpers/api.ts`.
- State management: Pinia (`stores/auth.ts`, `stores/loading.ts`)
- Styling: Tailwind CSS v4 + Lucide icons + Heroicons

**Caching & Storage:**
- Redis is used extensively for caching recipe lists, categories, and recipe detail (`setex` with 600s TTL).
- Image uploads use Tencent COS via the `cos` filesystem disk. CDN prefix prepended via `getenv('COS_CDN')`.
- ⚠️ **Critical**: The `overtrue/laravel-filesystem-cos` package requires a manual patch in `vendor/overtrue/laravel-filesystem-cos/src/CosStorageServiceProvider.php` to return an `Illuminate\Filesystem\FilesystemAdapter` instead of the raw Flysystem instance. Re-apply after every `composer install`.

**API Documentation:**
- L5-Swagger (`darkaonline/l5-swagger`) with `@OA` annotations on `AuthController` and `RecipeController`. Swagger UI available at `/swagger`.

## Key Configuration

- Database: MySQL 8.0 (default), Redis 7.x for caching
- App key generated via `php artisan key:generate`
- Environment variables: `COS_APP_ID`, `COS_SECRET_ID`, `COS_SECRET_KEY`, `COS_REGION`, `COS_BUCKET`, `COS_CDN`, `WECHAT_MINIPROGRAM_APPID`, `WECHAT_MINIPROGRAM_SECRET`
- Default frontend dev port: 5173 (Vite)
