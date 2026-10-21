# AGENTS.md

Laravel 12 JSON API + Vue 3 SPA for a recipe app. Backend in `app/`, SPA in `resources/assets/js/`. Runtime: PHP `^8.2` (tested with 8.2.9; Docker may run 8.4), MySQL 8, Redis optional, Vite 6 + Tailwind 4.

## Setup and commands

- `vendor/` is not committed and `composer.json` requires PHP `^8.2`. `composer` is not on PATH in this workspace, and the tracked `composer.phar` is Composer 1.6.5 (2018) — do not use it. Use Docker (`docker compose up`: nginx :80, Vite :5173, MySQL :3306, Redis :6379) or a PHP 8.2+ host with Composer 2. The host must have `openssl`, `mbstring`, and `pdo_sqlite` enabled for Composer/tests.
- The `php` container runs `composer install`, `migrate --force`, and `storage:link` on start; source is bind-mounted, so edits are live.
- Tests: `composer test` runs `vendor/bin/phpunit`; run `php artisan config:clear` first if the config is cached. Single class/method: `composer test -- --filter=RecipeApiTest` or `composer test -- --filter=test_can_login_user`. `php artisan test` is unavailable because `nunomaduro/collision` is not installed. In Docker: `docker compose exec php composer test`.
- Frontend: `npm run dev`, `npm run build`, `npm run lint` (ESLint `--fix`, it rewrites files), `npm run format` / `npm run format:check` (only `resources/`). Typecheck with `npx vue-tsc --noEmit`.
- Use npm, not yarn: CI uses `npm ci`, `package-lock.json` is tracked, and `yarn.lock` is gitignored.
- `composer dev` starts `php artisan serve`, the queue listener, and Vite via `concurrently`; it no longer invokes `pail`.
- Swagger UI is `/api/documentation` (not `/swagger`); regenerate with `php artisan l5-swagger:generate`. Docs scan `app/`, and `@OA\Server` reads `L5_SWAGGER_CONST_HOST` (defaults to `my-default-host.com`; set it in `.env`).

## Backend wiring

- `bootstrap/app.php` is the authoritative Laravel 12 bootstrap; `app/Http/Kernel.php` is unused. Providers load from `bootstrap/providers.php` and the legacy `config/app.php` list. `withRouting()` registers `routes/api.php` + `routes/web.php`; `App\Providers\RouteServiceProvider` is no longer registered, so keep route definitions in `routes/*.php` and don't add another registration path. `routes/recipe.php` is empty/unused.
- All API routes are in `routes/api.php`. Two URL styles coexist: dot endpoints (`/api/recipe.lists`, `recipe.info`, `recipe.category`, `recipe.search`, `recipe.daily`, `recipe.ai`, `recipe.personalized`, `POST recipe.generate`) and the resource (`/api/recipes`, `/api/recipes/{id}`).
- Favorites are mutated with GET (`recipe.favourite`, `recipe.unfavourite`, `recipe.isfavourite`) because the SPA calls them that way; don't change verbs without updating the frontend.
- `RecipeController` protects writes with `auth:sanctum`; `index, show, lists, info, category, search, daily, ai, personalized, generate, create` are public (`create` returns the create-form template). `index` and `lists` return paginators. `show`/`info` return the recipe at `data`.
- Auth uses Laravel Sanctum. `AuthController` returns the standard envelope for register/login/logout/WeChat login: `data.authenticated`, `data.user`, `data.api_token` (and `data.token` as an alias for the mini-program), `data.registered`, `data.done`. Register returns 201; logout requires auth.
- Everything uses `App\Http\Helpers\ApiResponse`: `{code, message, data}` with `code = 0` for success; paginated data is nested at `data.data` (see `tests/Unit/ApiResponseTest.php`). `ApiResponse::success` accepts an HTTP status and creates use 201.
- `app/Http/Controllers/Auth/*` and `Settings/*` are unused starter-kit controllers; API auth lives in `AuthController`.
- API messages are Chinese; match the existing style.

## Frontend

- Source is `resources/assets/js` (alias `@`), not `resources/js`. Entry `app.js`; views in `views/`, Pinia stores in `stores/`, axios wrapper in `helpers/api.ts`, routes in `router/index.js`.
- `app.js` imports `resources/assets/css/app.css` (Tailwind v3-style directives). `resources/css/app.css` is the Tailwind 4/shadcn stylesheet referenced by `components.json` but not imported by the app; verify which file applies before styling changes.
- `resources/views/welcome.blade.php` (the SPA fallback) loads only `resources/assets/js/app.js` via `@vite`; `vite.config.ts` uses the same single entry. Keep Vite inputs and Blade entries in sync when touching either.
- Axios `baseURL` is `VITE_API_BASE_URL || '/api'`; the token is read from `localStorage.user.api_token` and attached by the interceptor. A 401 clears the stored user and redirects to `/login` (with a `redirect` query).
- Views match the current API: `Recipe/Index.vue` reads the paginator at `data.data.data`, `Recipe/Show.vue` reads `data.data`, `Recipe/Form.vue` reads `data.data.form` + `data.data.categories`, and images use `recipe.image_src` with a `/images/{image}` fallback.
- `useFlash` is a module-level singleton, so messages raised in stores/views are shown by `AppNotification`.
- `public/hot` points Blade at `http://127.0.0.1:5173`. If Vite isn't running, delete it or start `npm run dev`, otherwise assets 404.

## Storage, cache, database

- Uploads always write to the local `images` disk (`public/images`, gitignored) and additionally to COS when `secret_id`, `secret_key`, and `bucket` are configured. `Recipe::image_src` returns `COS_CDN + image` when COS is configured, otherwise `/images/{image}`.
- `overtrue/laravel-filesystem-cos` needs the manual `vendor/.../CosStorageServiceProvider.php` patch documented in `readme.md`; re-apply/verify it after every `composer install` or `Storage::disk('cos')` methods break.
- `RecipeController` caches through `Cache::tags(['recipes'])` / `Cache::tags(['recipe_categories'])` for 600s. Taggable stores (Redis/array) are used when available; other stores or cache failures fall back to uncached queries. No direct `Redis` facade calls remain.
- `php artisan migrate --seed` runs `UsersTableSeeder`, which executes MySQL-only `SET FOREIGN_KEY_CHECKS=0` and truncates users/recipes. Don't seed SQLite with it.

## Tests and known traps

- `phpunit.xml` defaults tests to SQLite `:memory:` plus array cache/session/mail/queue; `tests/TestCase.php` applies `RefreshDatabase` globally and calls `withoutVite()`, so no MySQL/Redis/Vite is required.
- `tests/Browser/*` and `tests/DuskTestCase.php` are stale: `laravel/dusk` is not installed and PHPUnit only includes `tests/Unit` + `tests/Feature`.
- `tests/Feature/RecipeApiTest.php` covers the recipe/auth contract. Use `Storage::fake('images')` and `Storage::fake('cos')` for upload tests; set COS config explicitly when asserting CDN URLs.
- Root `test_api_concurrency.js` targets `localhost:5000/translate`; it is unrelated to this app.
- CI has two workflows: `.github/workflows/node.js.yml` runs `npm ci` + `npm run build`, and `.github/workflows/tests.yml` runs the PHP test suite on PHP 8.4.
- `CLAUDE.md` is an older overview; where it conflicts with this file or the code, trust this file/code.
