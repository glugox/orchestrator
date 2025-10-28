<?php

namespace Glugox\Orchestrator;

use Illuminate\Contracts\Support\Arrayable;
use function is_dir;

class ModuleDescriptor implements Arrayable
{
    protected bool $installed;
    protected bool $enabled;

    /**
     * @param  array<string, mixed>  $paths
     * @param  array<int, string>  $providers
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        protected string $id,
        protected string $name,
        protected string $version,
        bool $installed,
        bool $enabled,
        protected string $basePath,
        protected array $paths = [],
        protected array $providers = [],
        protected array $capabilities = [],
        protected array $extra = []
    ) {
        $this->installed = $installed;
        $this->enabled = $enabled && $installed;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function basePathExists(): bool
    {
        $path = $this->basePath();

        return $path !== '' && is_dir($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isHealthy(): bool
    {
        return $this->healthStatus() === 'healthy';
    }

    public function healthStatus(): string
    {
        if (! $this->isInstalled()) {
            return 'not installed';
        }

        if (! $this->basePathExists()) {
            return 'missing files';
        }

        if (! $this->isEnabled()) {
            return 'disabled';
        }

        return 'healthy';
    }

    public function enable(): void
    {
        $this->installed = true;
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function uninstall(): void
    {
        $this->installed = false;
        $this->enabled = false;
    }

    public function markInstalled(bool $installed): void
    {
        $this->installed = $installed;
        if (! $installed) {
            $this->enabled = false;
        }
    }

    public function markEnabled(bool $enabled): void
    {
        if ($enabled) {
            $this->enable();

            return;
        }

        $this->disable();
    }

    public function path(?string $subPath = null): string
    {
        if ($subPath === null || $subPath === '') {
            return $this->basePath;
        }

        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($subPath, DIRECTORY_SEPARATOR);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'installed' => $this->installed,
            'enabled' => $this->enabled,
            'base_path' => $this->basePath,
            'paths' => $this->paths,
            'providers' => $this->providers,
            'capabilities' => $this->capabilities,
            'extra' => $this->extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            $attributes['id'],
            $attributes['name'] ?? $attributes['id'],
            $attributes['version'] ?? '0.0.0',
            (bool) ($attributes['installed'] ?? true),
            (bool) ($attributes['enabled'] ?? ($attributes['installed'] ?? true)),
            $attributes['base_path'] ?? '',
            is_array($attributes['paths'] ?? null) ? $attributes['paths'] : [],
            self::stringArray($attributes['providers'] ?? []),
            self::stringArray($attributes['capabilities'] ?? []),
            is_array($attributes['extra'] ?? null) ? $attributes['extra'] : []
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int, string>
     */
    protected static function stringArray(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
