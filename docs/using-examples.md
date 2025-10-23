# Using the Example Modules in a Laravel Application

The `examples/` directory contains lightweight feature modules that demonstrate how the orchestrator package discovers metadata, registers service providers, and gates functionality behind the module manifest. The steps below assume a standard Laravel application that already requires `glugox/orchestrator`.

## 1. Expose the modules to Composer

Add the local path repositories to your application's `composer.json` so Composer can install the modules alongside the orchestrator package:

```json
{
    "repositories": [
        {"type": "path", "url": "../packages/orchestrator/examples/acme-blog", "options": {"symlink": true}},
        {"type": "path", "url": "../packages/orchestrator/examples/acme-analytics", "options": {"symlink": true}}
    ],
    "require": {
        "glugox/orchestrator": "dev-main",
        "acme/blog-module": "*@dev",
        "acme/analytics-module": "*@dev"
    }
}
```

Adjust the repository paths to reflect where the orchestrator package lives relative to your Laravel app.

## 2. Publish and review the orchestrator configuration

Within the Laravel app run:

```bash
php artisan vendor:publish --provider="Glugox\\Orchestrator\\OrchestratorServiceProvider"
```

The generated `config/orchestrator.php` controls where the manager looks for modules and where the manifest cache is stored. Ensure the following options are aligned with your project layout:

- `base_path`: project root, e.g. `base_path()` in Laravel.
- `installed_path`: path to Composer's `installed.json` (defaults to `vendor/composer/installed.json`).
- `module_json_paths`: include additional glob patterns if you store standalone `module.json` files outside of Composer packages.

## 3. Run discovery and enable the modules

When `ModuleManager` boots it reads the Composer metadata and writes the manifest cache. Warm the manifest by running:

```bash
php artisan modules:cache
```

You can now enable modules explicitly if auto-enable is disabled:

```bash
php artisan modules:enable acme/blog
php artisan modules:enable acme/analytics
```

The included example configuration enables modules automatically on install, so the commands above are optional unless you customise the behaviour.

## 4. Interact with the example features

### Acme Blog

- **Service provider**: `Acme\\Blog\\Providers\\BlogServiceProvider` registers views, translations, and the `/blog` web route only when `Modules::enabled('acme/blog')` evaluates to `true`.
- **Migrations & seeders**: The module ships with a `blog_posts` migration and `Acme\\Blog\\Database\\Seeders\\BlogSeeder` to populate sample data.
- **Configuration**: `config/module.php` exposes a `posts_per_page` setting which becomes available under `config('modules.acme/blog.posts_per_page')` after registration.

Visit `https://your-app.test/blog` to render the Blade view defined in `resources/views/posts/index.blade.php`.

### Acme Analytics

- **Service provider**: `Acme\\Analytics\\Providers\\AnalyticsServiceProvider` loads view and translation resources and registers an authenticated dashboard route (`/analytics`).
- **Capabilities**: The module declares `http:web` capability only, demonstrating a read-only UI feature without database integrations.

Access `https://your-app.test/analytics` as an authenticated user to load the dashboard view at `resources/views/dashboard/index.blade.php`.

## 5. Working with module state in application code

The orchestrator exposes a facade for querying module state. Inside your Laravel app you can guard features like so:

```php
use Glugox\Orchestrator\Facades\Modules;

if (Modules::enabled('acme/analytics')) {
    // Show analytics UI or register additional observers.
}
```

This mirrors the conditional checks used inside the example service providers to ensure routes, observers, or scheduled tasks respect the orchestrator's enable/disable lifecycle.
