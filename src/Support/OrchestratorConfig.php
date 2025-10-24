<?php

namespace Glugox\Orchestrator\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class OrchestratorConfig
{
    protected string $basePath;

    protected string $manifestPath;

    protected string $installedJsonPath;

    /**
     * @var array<int, string>
     */
    protected array $moduleJsonPatterns;

    protected bool $autoInstall;

    protected bool $autoEnable;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->basePath = $this->resolveBasePath($config['base_path'] ?? null);
        $this->manifestPath = $this->absolutePath($config['manifest_path'] ?? 'bootstrap/cache/modules.php');
        $this->installedJsonPath = $this->absolutePath($config['installed_path'] ?? 'vendor/composer/installed.json');
        $this->moduleJsonPatterns = $this->normalisePatterns($config['module_json_paths'] ?? []);
        $this->autoInstall = (bool) ($config['auto_install'] ?? true);
        $this->autoEnable = (bool) ($config['auto_enable'] ?? true);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    public function installedJsonPath(): string
    {
        return $this->installedJsonPath;
    }

    /**
     * @return array<int, string>
     */
    public function moduleJsonPatterns(): array
    {
        return $this->moduleJsonPatterns;
    }

    public function autoInstall(): bool
    {
        return $this->autoInstall;
    }

    public function autoEnable(): bool
    {
        return $this->autoEnable;
    }

    public function absolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->canonicalizePath($path);
        }

        return $this->canonicalizePath($this->basePath.DIRECTORY_SEPARATOR.$path);
    }

    public function canonicalizePath(string $path): string
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

    protected function resolveBasePath(mixed $configured): string
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

    protected function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\']) || (strlen($path) > 1 && $path[1] === ':');
    }

    /**
     * @param  mixed  $patterns
     * @return array<int, string>
     */
    protected function normalisePatterns(mixed $patterns): array
    {
        $patterns = Arr::wrap($patterns);
        $result = [];

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $result[] = $pattern;
        }

        if ($result === []) {
            $result = [
                'vendor/*/*/module.json',
                'modules/*/module.json',
                'packages/*/*/module.json',
            ];
        }

        return array_values(array_unique($result));
    }
}
