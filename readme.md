# Single Page Application with Laravel 12  - Recipe Box

### Frameworks and Libraries:

- Laravel 12
- Axios

### Provides:

- Built in token authentication
- Image preview and upload
- Nested forms
- CRUD


### Installation


`php artisan key:generate`

`composer install`

`npm install`

`npm run dev`


非常好的习惯！**对第三方包源码的修改一定要在项目的 `README.md` 里记录清楚**，否则后续升级或团队协作时容易丢失或踩坑。

---

## 推荐写法（中英文双语，便于团队协作）

### 在 `README.md` 里添加如下内容：

---

### ⚠️ 重要说明：COS 文件系统驱动的兼容性修正

#### [EN] Important: Patch for overtrue/laravel-filesystem-cos ServiceProvider

我们对 `vendor/overtrue/laravel-filesystem-cos/src/CosStorageServiceProvider.php` 做了如下修改，以兼容 Laravel 12 的 Storage 适配器机制：

> We have modified `vendor/overtrue/laravel-filesystem-cos/src/CosStorageServiceProvider.php` to ensure compatibility with Laravel 12 Storage system:

**修改内容 / Patch:**

```php
// use League\Flysystem\Filesystem;
// use Overtrue\Flysystem\Cos\CosAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;

public function boot()
{
    Storage::extend('cos', function ($app, $config) {
        $adapter = new \Overtrue\Flysystem\Cos\CosAdapter($config);
        $filesystem = new \League\Flysystem\Filesystem($adapter);

        // 必须返回 FilesystemAdapter，否则 Storage facade 无法正常代理 put/get 等方法
        return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
    });
}
```

**原因 / Reason:**
- 官方包默认返回 Flysystem 的 Filesystem 实例，导致 `Storage::disk('cos')` 不是 Laravel 的 FilesystemAdapter，无法正常使用 `put()`、`get()` 等方法。
- 该修改保证了 Laravel 12 下 Storage facade 的所有方法都能正常代理到 COS 驱动。

**注意 / Note:**
- 每次升级 `overtrue/laravel-filesystem-cos` 包时，请务必重新应用此修改！
- 建议后续关注官方包的更新，若官方修复此兼容性问题，可移除此 patch。




