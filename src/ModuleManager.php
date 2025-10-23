<?php

namespace Glugox\Orchestrator;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ModuleManager
{
    /**
     * @var array<string, ModuleDescriptor>
     */
    protected array $modules = [];

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected string $basePath;

    protected ModuleManifest $manifest;

    protected string $installedPath;

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('orchestrator', []);
        $this->basePath = $this->resolveBasePath($this->config['base_path'] ?? null);
        $manifestPath = $this->absolutePath($this->config['manifest_path'] ?? 'bootstrap/cache/modules.php');
        $this->manifest = new ModuleManifest($manifestPath);
        $this->installedPath = $this->absolutePath($this->config['installed_path'] ?? 'vendor/composer/installed.json');

        $this->modules = $this->loadCachedModules();

        if ($this->modules === []) {
            $this->discover();
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function manifestPath(): string
    {
        return $this->manifest->path();
    }

    public function isCached(): bool
    {
        return $this->manifest->exists();
    }

    /**
     * @return Collection<int, ModuleDescriptor>
     */
    public function all(): Collection
    {
        return Collection::make(array_values($this->modules));
    }

    /**
     * @return Collection<int, ModuleDescriptor>
     */
    public function installed(): Collection
    {
        return $this->all()->filter(fn (ModuleDescriptor $module) => $module->isInstalled())->values();
    }

    /**
     * @return Collection<int, ModuleDescriptor>
     */
    public function enabledModules(): Collection
    {
        return $this->all()->filter(fn (ModuleDescriptor $module) => $module->isEnabled())->values();
    }

    public function module(string $id): ModuleDescriptor
    {
        $module = $this->modules[$id] ?? null;

        if (! $module) {
            throw new InvalidArgumentException(sprintf('Module [%s] is not registered in the orchestrator manifest.', $id));
        }

        return $module;
    }

    public function path(string $id, ?string $subPath = null): string
    {
        return $this->module($id)->path($subPath);
    }

    public function isEnabled(string $id): bool
    {
        return $this->module($id)->isEnabled();
    }

    public function enabled(string $id): bool
    {
        return $this->isEnabled($id);
    }

    public function install(string $id): void
    {
        $module = $this->module($id);
        $module->markInstalled(true);
        $module->markEnabled($module->isEnabled() || ($this->config['auto_enable'] ?? true));
        $this->persist();
    }

    public function uninstall(string $id, bool $dropData = false): void
    {
        $module = $this->module($id);
        $module->uninstall();
        $this->persist();
    }

    public function enable(string $id): void
    {
        $module = $this->module($id);
        $module->markEnabled(true);
        $this->persist();
    }

    public function disable(string $id): void
    {
        $module = $this->module($id);
        $module->markEnabled(false);
        $this->persist();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function migrate(string $id, array $options = []): int
    {
        $module = $this->module($id);
        $paths = $module->paths();
        $migrations = $paths['migrations'] ?? null;

        if (! $migrations || ! class_exists('Illuminate\\Support\\Facades\\Artisan') || ! function_exists('app')) {
            return 0;
        }

        $migrationPath = is_array($migrations) ? ($migrations[0] ?? null) : $migrations;

        if (! is_string($migrationPath) || $migrationPath === '') {
            return 0;
        }

        $arguments = array_merge(['--path' => $module->path($migrationPath)], $options);

        /** @var \Illuminate\Support\Facades\Artisan $artisan */
        $artisan = app('artisan');

        return $artisan->call('migrate', $arguments);
    }

    public function seed(string $id, ?string $class = null): void
    {
        $module = $this->module($id);
        $paths = $module->paths();
        $seeders = $paths['seeds'] ?? null;

        if (! $seeders || ! class_exists('Illuminate\\Support\\Facades\\Artisan') || ! function_exists('app')) {
            return;
        }

        $arguments = [];

        if ($class) {
            $arguments['--class'] = $class;
        }

        /** @var \Illuminate\Support\Facades\Artisan $artisan */
        $artisan = app('artisan');
        $artisan->call('db:seed', $arguments);
    }

    public function discover(bool $writeManifest = true): array
    {
        $this->modules = $this->discoverModules();

        if ($writeManifest) {
            $this->persist();
        }

        return $this->modules;
    }

    public function cache(): void
    {
        $this->persist();
    }

    public function clearCache(): void
    {
        $this->manifest->delete();
    }

    protected function persist(): void
    {
        $payload = [];

        foreach ($this->modules as $module) {
            $payload[$module->id()] = $module->toArray();
        }

        $this->manifest->write($payload);
    }

    /**
     * @return array<string, ModuleDescriptor>
     */
    protected function loadCachedModules(): array
    {
        if (! $this->manifest->exists()) {
            return [];
        }

        $data = $this->manifest->load();
        $modules = [];

        foreach ($data as $attributes) {
            if (! is_array($attributes) || ! isset($attributes['id'])) {
                continue;
            }

            $descriptor = ModuleDescriptor::fromArray($attributes);
            $modules[$descriptor->id()] = $descriptor;
        }

        return $modules;
    }

    /**
     * @return array<string, ModuleDescriptor>
     */
    protected function discoverModules(): array
    {
        $state = [];

        foreach ($this->modules as $module) {
            $state[$module->id()] = [
                'installed' => $module->isInstalled(),
                'enabled' => $module->isEnabled(),
            ];
        }

        foreach ($this->manifest->load() as $attributes) {
            if (! is_array($attributes) || ! isset($attributes['id'])) {
                continue;
            }

            $state[$attributes['id']] = [
                'installed' => (bool) ($attributes['installed'] ?? ($this->config['auto_install'] ?? true)),
                'enabled' => (bool) ($attributes['enabled'] ?? ($this->config['auto_enable'] ?? true)),
            ];
        }

        $modules = [];

        foreach ($this->discoverFromComposer() as $module) {
            $override = $state[$module->id()] ?? null;

            if ($override) {
                $module->markInstalled($override['installed']);
                $module->markEnabled($override['enabled']);
            }

            $modules[$module->id()] = $module;
        }

        foreach ($this->discoverFromModuleJson() as $module) {
            if (isset($modules[$module->id()])) {
                continue;
            }

            $override = $state[$module->id()] ?? null;

            if ($override) {
                $module->markInstalled($override['installed']);
                $module->markEnabled($override['enabled']);
            }

            $modules[$module->id()] = $module;
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @return array<int, ModuleDescriptor>
     */
    protected function discoverFromComposer(): array
    {
        $packages = $this->readComposerPackages();
        $modules = [];

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $meta = Arr::get($package, 'extra.glugox-module', []);

            if (($package['type'] ?? null) !== 'laravel-module' && empty($meta)) {
                continue;
            }

            $id = $meta['id'] ?? $package['name'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $modules[] = new ModuleDescriptor(
                $id,
                $meta['name'] ?? ($package['name'] ?? $id),
                $meta['version'] ?? ($package['version'] ?? $package['pretty_version'] ?? '0.0.0'),
                (bool) ($this->config['auto_install'] ?? true),
                (bool) ($this->config['auto_enable'] ?? true),
                $this->resolveInstallPath($package),
                $this->normalisePaths($meta),
                $this->normaliseProviders(Arr::get($package, 'extra.laravel.providers', [])),
                $this->normaliseArray($meta['capabilities'] ?? []),
                is_array($meta) ? $meta : []
            );
        }

        return $modules;
    }

    /**
     * @return array<int, ModuleDescriptor>
     */
    protected function discoverFromModuleJson(): array
    {
        $paths = $this->config['module_json_paths'] ?? [];
        $modules = [];

        foreach ($paths as $pattern) {
            $pattern = $this->absolutePath($pattern);
            $files = glob($pattern) ?: [];

            foreach ($files as $file) {
                $json = json_decode((string) file_get_contents($file), true);

                if (! is_array($json) || ! isset($json['id'])) {
                    continue;
                }

                $modules[] = new ModuleDescriptor(
                    $json['id'],
                    $json['name'] ?? $json['id'],
                    $json['version'] ?? '0.0.0',
                    (bool) ($this->config['auto_install'] ?? true),
                    (bool) ($this->config['auto_enable'] ?? true),
                    $this->canonicalizePath(dirname($file)),
                    $this->normalisePaths($json),
                    $this->normaliseArray($json['providers'] ?? []),
                    $this->normaliseArray($json['capabilities'] ?? []),
                    $json
                );
            }
        }

        return $modules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readComposerPackages(): array
    {
        if (! is_file($this->installedPath)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($this->installedPath), true);

        if (! is_array($json)) {
            return [];
        }

        if (isset($json['packages']) && is_array($json['packages'])) {
            return $json['packages'];
        }

        if (Arr::isAssoc($json) && isset($json['name'])) {
            return [$json];
        }

        $packages = [];

        foreach ($json as $entry) {
            if (isset($entry['packages']) && is_array($entry['packages'])) {
                $packages = array_merge($packages, $entry['packages']);
            } elseif (is_array($entry) && isset($entry['name'])) {
                $packages[] = $entry;
            }
        }

        return $packages;
    }

    protected function resolveBasePath(?string $configured): string
    {
        if (is_string($configured) && $configured !== '') {
            return $this->canonicalizePath($configured);
        }

        if (function_exists('base_path')) {
            return base_path();
        }

        $cwd = getcwd();

        if (! is_string($cwd) || $cwd === '') {
            throw new RuntimeException('Unable to determine application base path for the orchestrator.');
        }

        return $cwd;
    }

    protected function resolveInstallPath(array $package): string
    {
        $path = $package['install_path'] ?? $package['install-path'] ?? null;

        if (is_string($path) && $path !== '') {
            $composerDir = dirname($this->installedPath);
            $candidate = $this->canonicalizePath($composerDir.DIRECTORY_SEPARATOR.$path);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        $name = $package['name'] ?? null;

        if (is_string($name) && $name !== '') {
            return $this->absolutePath('vendor/'.$name);
        }

        return $this->basePath;
    }

    protected function absolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->canonicalizePath($path);
        }

        return $this->canonicalizePath($this->basePath.DIRECTORY_SEPARATOR.$path);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\']) || (strlen($path) > 1 && $path[1] === ':');
    }

    protected function canonicalizePath(string $path): string
    {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $stack = [];
        $prefix = '';

        if ($path !== '' && ($path[0] ?? '') === DIRECTORY_SEPARATOR) {
            $prefix = DIRECTORY_SEPARATOR;
        } elseif (isset($segments[0]) && Str::contains($segments[0], ':')) {
            $prefix = array_shift($segments).DIRECTORY_SEPARATOR;
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($stack);
                continue;
            }

            $stack[] = $segment;
        }

        $resolved = implode(DIRECTORY_SEPARATOR, $stack);

        return $prefix.$resolved;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, string>
     */
    protected function normaliseArray(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  mixed  $providers
     * @return array<int, string>
     */
    protected function normaliseProviders($providers): array
    {
        if (is_string($providers)) {
            return [$providers];
        }

        if (! is_array($providers)) {
            return [];
        }

        return $this->normaliseArray($providers);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function normalisePaths(array $meta): array
    {
        $paths = [];
        $keys = ['routes', 'migrations', 'seeds', 'views', 'translations'];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $meta)) {
                continue;
            }

            $value = $meta[$key];

            if (is_array($value)) {
                $paths[$key] = array_values(array_filter($value, fn ($path) => is_string($path) && $path !== ''));
            } elseif (is_string($value) && $value !== '') {
                $paths[$key] = $value;
            }
        }

        return $paths;
    }
}
