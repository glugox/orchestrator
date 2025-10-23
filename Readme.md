# Laravel Modular App — Main Orchestrator Package Design

> **Goal:** Build a very large Laravel app where nearly all business code lives in versioned **modules** (Composer packages), while the Laravel app repo remains a thin shell. This document specifies the **main orchestrator package** that discovers, installs, enables, boots, and observes modules at scale.

---

## 1) Naming & Scope

- **Main package**: `glugox/orchestrator` (core orchestrator for module lifecycle)
- **Module SDK**: `glugox/module-kit` (interfaces, traits, testing helpers, skeletons)
- **Optional utilities**:
    - `glugox/composer-helper` (manipulate root composer.json; path repos, stability)
    - `glugox/actions` (action runner / progress logging)
    - `glugox/magic` (scaffolding & file ops you already have; optional integration)

> The app repo depends on `glugox/orchestrator` and installs individual feature modules (e.g., `company/billing`, `company/hr`, `company/cms`) via Composer.

---

## 2) High‑Level Architecture

```
laravel-app/
  app/       # thin (ideally empty besides Providers/Auth)
  bootstrap/
  config/
  public/
  storage/
  vendor/
  composer.json  # declares modules and orchestrator

packages/ (optional for local path dev)
  company/billing
  company/hr
  company/cms
examples/
  acme-blog
  acme-analytics
```

> Explore the reference modules under [`examples/`](examples/) and the accompanying integration guide in [`docs/using-examples.md`](docs/using-examples.md) for a hands-on walkthrough of how the orchestrator boots real features inside a host Laravel application.

**Orchestrator responsibilities**
1. **Discovery**: Find modules via Composer metadata (preferred) and/or `module.json`.
2. **State**: Track installed/enabled versions in DB + cached manifest.
3. **Booting**: Register service providers, routes, migrations, views, policies, translations, events, observers.
4. **Lifecycle**: install → enable/disable → upgrade → uninstall; run migrations & seeds per module.
5. **Isolation**: Clear namespaces, config keys, table name prefixes (optional), event domains.
6. **Tooling**: CLI for all lifecycle tasks + health checks.
7. **Performance**: Cache manifests, defer providers, route & config cache aware.

---

## 3) Module Contract (SDK)

Each module implements a small contract so the orchestrator can treat all consistently.

```php
namespace Glugox\ModuleKit;

interface ModuleContract
{
    /** Unique id, e.g. "company/billing" */
    public function id(): string;

    /** Semantic version of this release */
    public function version(): string;

    /** Human name */
    public function name(): string;

    /** Capabilities the module exposes */
    public function capabilities(): array; // e.g. ['http:web','http:api','db:migrations','ui:inertia']

    /** Called on first install (idempotent). */
    public function install(): void;

    /** Called when enabling the module. */
    public function enable(): void;

    /** Called when disabling the module (keeps data). */
    public function disable(): void;

    /** Called before uninstall (danger: may drop data). */
    public function uninstall(): void;
}
```

**Base Service Provider** (per module):

```php
abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleId;   // e.g. 'company/billing'
    protected string $basePath;   // module base path

    public function register()
    {
        // Merge config with namespacing: config("modules.{$this->moduleId}.<key>")
        $this->mergeConfigFrom($this->basePath.'/config/module.php', "modules.{$this->moduleId}");

        // Bind module services
        $this->app->singleton($this->moduleId.'.kernel', fn() => new ModuleKernel());
    }

    public function boot()
    {
        // Views / translations
        $this->loadViewsFrom($this->basePath.'/resources/views', str_replace('/', '__', $this->moduleId));
        $this->loadTranslationsFrom($this->basePath.'/resources/lang', str_replace('/', '__', $this->moduleId));

        // Routes (conditionally via orchestrator state)
        if (\Glugox\Orchestrator\Facades\Modules::enabled($this->moduleId)) {
            $this->mapRoutes();
            $this->registerPolicies();
            $this->registerEvents();
        }

        // Publishable assets (optional)
        $this->publishes([
            $this->basePath.'/public' => public_path('vendor/'.str_replace('/', '-', $this->moduleId))
        ], 'modules-assets');
    }

    protected function mapRoutes(): void
    {
        if (file_exists($f = $this->basePath.'/routes/web.php')) {
            Route::middleware(['web'])
                ->name(str_replace('/', '.', $this->moduleId).'.')
                ->group($f);
        }
        if (file_exists($f = $this->basePath.'/routes/api.php')) {
            Route::prefix('api')
                ->middleware(['api'])
                ->name(str_replace('/', '.', $this->moduleId).'.api.')
                ->group($f);
        }
    }

    protected function registerPolicies(): void { /* ... */ }
    protected function registerEvents(): void { /* ... */ }
}
```

---

## 4) Module Metadata (Composer‑first)

**composer.json (in each module)**
```json
{
  "name": "company/billing",
  "type": "laravel-module",
  "autoload": { "psr-4": { "Company\\Billing\\": "src/" } },
  "extra": {
    "laravel": {
      "providers": ["Company\\Billing\\BillingServiceProvider"]
    },
    "glugox-module": {
      "id": "company/billing",
      "routes": ["routes/web.php", "routes/api.php"],
      "migrations": "database/migrations",
      "seeds": "database/seeders",
      "views": "resources/views",
      "translations": "resources/lang",
      "capabilities": ["http:web","http:api","db:migrations"],
      "requires": {"php": ">=8.2", "laravel/framework": ">=11.0"}
    }
  }
}
```

**Alternative**: `module.json` for non‑Laravel packages; orchestrator supports both.

---

## 5) Orchestrator Core

### 5.1 Database State

- `modules` table:
    - `id` (string, PK like `company/billing`)
    - `version` (string)
    - `enabled` (bool)
    - `installed_at`, `enabled_at`, `disabled_at`
    - `payload` (json: capabilities, paths, provider class, checksum)

- `module_migrations` table: tracks per‑module migration status (optional if using Laravel’s default plus namespacing).

### 5.2 Manifest & Cache

- Build a **manifest** at runtime or via `modules:cache`:
    - Map: module id → provider, paths, capabilities, version, enabled
    - Stored in `bootstrap/cache/modules.php`

### 5.3 Discovery

- Read **Composer installed.json** + package `extra.glugox-module`.
- Fallback: search `vendor/*/*/(module.json)`.
- Merge with DB state to decide boot order and enabled set.

### 5.4 ModuleManager (Facade: `Modules`)

```php
final class ModuleManager
{
    /** @return Collection<ModuleDescriptor> */
    public function all(): Collection {}
    public function enabled(): Collection {}
    public function installed(): Collection {}

    public function enable(string $id): void {}
    public function disable(string $id): void {}
    public function install(string $id): void {}
    public function uninstall(string $id, bool $dropData = false): void {}

    public function migrate(string $id, array $options = []): int {}
    public function seed(string $id, ?string $class = null): void {}

    public function path(string $id, ?string $sub = null): string {}
    public function isEnabled(string $id): bool {}
}
```

Facade:
```php
class Modules extends Facade { protected static function getFacadeAccessor() { return ModuleManager::class; } }
```

### 5.5 Boot Sequence (AppServiceProvider@boot)

1. Load **cached manifest** if present; else build and cache.
2. Register only **enabled** modules’ providers.
3. Defer heavy bits if `config('app.env') === 'production'`.

---

## 6) Routing, Migrations, Config

### 6.1 Routing
- Namespacing: route names start with module id → `company.billing.*`.
- Middleware stacks are declared by module; orchestrator can enforce global constraints (e.g., tenant, auth).
- Route caching supported: orchestrator writes a **merged routes file** for enabled modules.

### 6.2 Migrations
- Each module ships its migrations under its own folder.
- Orchestrator runs them with `--path` pointing to the module folder.
- Optionally maintain a `module_migrations` ledger to prevent collisions.

### 6.3 Config merging & precedence
- Default precedence: **app config** overrides **module config**.
- Provide `config/modules.php` flags:
    - `prefer_module_config: false|true` (per key or per module)
    - `blocked_keys: [...]` to prevent a module from overriding critical settings.

---

## 7) CLI Commands

```bash
php artisan modules:discover           # rebuild manifest from composer + module.json
php artisan modules:cache              # write bootstrap/cache/modules.php
php artisan modules:list               # table view with statuses
php artisan modules:install billing    # install (run install hooks)
php artisan modules:enable billing
php artisan modules:disable billing
php artisan modules:migrate billing --seed
php artisan modules:uninstall billing --drop-data
php artisan modules:doctor             # health checks, route/migration conflicts
```

Command example:
```php
#[AsCommand(name: 'modules:migrate')]
class ModuleMigrate extends Command
{
    protected $signature = 'modules:migrate {id?} {--seed}';
    public function handle(ModuleManager $modules) {
        $ids = $this->argument('id') ? [$this->argument('id')] : $modules->enabled()->pluck('id');
        foreach ($ids as $id) {
            $count = $modules->migrate($id);
            $this->info("[$id] migrated $count step(s)");
            if ($this->option('seed')) $modules->seed($id);
        }
    }
}
```

---

## 8) Frontend (Inertia/Vue or Livewire)

- Each module may include `resources/js/<module>` with routes/components.
- Orchestrator exposes a **Vite plugin** that autoloads module entries:
    - Scans enabled modules for `module.entry.ts` and injects them into build.
    - Compiles to separate chunks per module to reduce initial bundle.
- UI asset namespace: `/vendor/modules/<module-id>/...`

---

## 9) Permissions & Policies

- Integrate with `spatie/laravel-permission` (optional):
    - Modules declare `permissions.php`:
      ```php
      return [ 'billing.view', 'billing.pay', 'billing.refund' ];
      ```
    - Orchestrator can auto‑sync on install/enable.
- Policies are registered by module provider; namespaced models prevent clashes.

---

## 10) Events, Jobs, Observers

- Domain events are namespaced per module: `Company\Billing\Events\InvoicePaid`.
- Cross‑module communication via **events** and **contracts** only (avoid direct class coupling).
- Queue names can be prefixed by module id: `billing-high`, `billing-low`.

---

## 11) Multitenancy (Optional Layer)

- If needed, add an optional `glugox/tenancy-bridge`:
    - Tenancy middleware is applied centrally; modules declare `tenantAware: true` in metadata.
    - Orchestrator enforces per‑tenant migrations by scoping connection or schema.

---

## 12) Performance & Scalability

- **Caches**: modules manifest cache, route cache, config cache, view cache.
- **Deferred providers**: only bind heavy services on demand.
- **Chunked boot**: large apps split module boot into phases (config → providers → routes).
- **Health checks**: collision detector for route names, config keys, migration class names.

---

## 13) Testing Strategy (Pest + Testbench)

- Each module is tested standalone using **Orchestra Testbench**.
- Orchestrator provides `ModuleTestCase` to boot only selected modules for a suite.
- Provide **fixtures**: fake tenant, fake user with permissions, db refresh helpers.

```php
uses(ModuleTestCase::class)->in('tests');

it('creates invoice', function () {
    withModules(['company/billing']);
    // your test
});
```

---

## 14) Minimal Host App Setup

1. **Install orchestrator** and modules via Composer.
2. Add `Glugox\Orchestrator\OrchestratorServiceProvider` to `config/app.php` (or rely on package discovery).
3. Publish orchestrator config: `php artisan vendor:publish --tag=glugox-orchestrator-config`.
4. Migrate: `php artisan migrate` (creates `modules` tables).
5. Discover & cache: `php artisan modules:discover && php artisan modules:cache`.
6. Enable modules: `php artisan modules:enable company/billing`.

**App stays thin**: feature code lives in modules.

---

## 15) Module Skeleton (Generator)

Orchestrator ships a generator:

```bash
php artisan module:make company/billing --in=packages/company/billing
```

Generates:
```
packages/company/billing/
  composer.json
  module.json (optional)
  src/BillingServiceProvider.php
  src/Http/Controllers/...
  routes/web.php
  routes/api.php
  database/migrations/2025_10_23_000000_create_billing_tables.php
  database/seeders/BillingSeeder.php
  resources/views/
  resources/lang/
  config/module.php
```

---

## 16) Composer Helper (optional but handy)

```php
final class ComposerHelper
{
    public function __construct(private string $composerJsonPath) {}

    public function ensureLocalRepo(string $absPath, bool $symlink = true): void { /* writes repositories[] */ }
    public function setMinimumStability(string $stability = 'dev'): void { /* writes minimum-stability */ }
}
```

Use to add local path repos for active development:
```php
$ch = new ComposerHelper(base_path('composer.json'));
$ch->ensureLocalRepo(base_path('packages/company/billing'));
$ch->setMinimumStability('dev');
```

---

## 17) Safety & Governance

- **Guard rails**: modules run inside app process; add allow‑lists for:
    - Env vars a module can read
    - Config keys a module can write
    - Queues/channels it can register
- **Code owners** and **version policies** per module; orchestrator enforces minimum versions.
- **Signed releases** (optional): verify signatures before enabling.

---

## 18) Example: Billing Module Provider (short)

```php
final class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleId = 'company/billing';

    public function __construct($app)
    {
        parent::__construct($app);
        $this->basePath = __DIR__.'/..';
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Models\Invoice::class, Policies\InvoicePolicy::class);
    }
}
```

---

## 19) Roadmap

- v0.1: discovery, state, basic lifecycle, routes/migrations/views, CLI.
- v0.2: vite autoload, permissions sync, collision doctor.
- v0.3: multitenancy bridge, signed releases, module marketplace UI.