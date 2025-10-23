<?php

namespace Glugox\Orchestrator;

use Illuminate\Support\ServiceProvider;

class OrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orchestrator.php', 'orchestrator');

        $this->app->singleton(ModuleManager::class, function ($app) {
            $config = $app['config']['orchestrator'] ?? [];

            return new ModuleManager(is_array($config) ? $config : []);
        });

        $this->app->alias(ModuleManager::class, 'modules');
        $this->app->alias(ModuleManager::class, 'orchestrator');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/orchestrator.php' => $this->configPath('orchestrator.php'),
        ], 'orchestrator-config');

        $this->publishes([
            __DIR__.'/../config/orchestrator.php' => $this->configPath('orchestrator.php'),
        ], 'config');
    }

    protected function configPath(string $path): string
    {
        if (method_exists($this->app, 'configPath')) {
            return $this->app->configPath($path);
        }

        if (function_exists('config_path')) {
            return config_path($path);
        }

        return $this->app->basePath('config/'.$path);
    }
}
