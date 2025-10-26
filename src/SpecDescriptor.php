<?php

namespace Glugox\Orchestrator;

use Illuminate\Contracts\Support\Arrayable;

class SpecDescriptor implements Arrayable
{

    public function __construct(
        protected string $id,
        protected string $name,
        protected string $namespace,
        protected string $configPath,
        protected bool $isEnabled = true,
    ) {

    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function configPath(): string
    {
        return $this->configPath;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'namespace' => $this->namespace,
            'config_path' => $this->configPath,
            'is_enabled' => $this->isEnabled,
        ];
    }

    public function toString(): string
    {
        return "{$this->name} ({$this->id}), Namespace: {$this->namespace}, Config: {$this->configPath}";
    }
}
