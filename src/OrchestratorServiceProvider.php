<?php

namespace Glugox\Orchestrator;

use Glugox\Orchestrator\Commands\DisableModuleCommand;
use Glugox\Orchestrator\Commands\EnableModuleCommand;
use Glugox\Orchestrator\Commands\ListModulesCommand;
use Glugox\Orchestrator\Commands\ReloadModulesCommand;
use Glugox\Orchestrator\Services\ModuleRegistry;
use Glugox\Orchestrator\Support\ModuleDiscovery;
use Glugox\Orchestrator\Support\OrchestratorConfig;
use Illuminate\Support\ServiceProvider;

class OrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orchestrator.php', 'orchestrator');

        $this->app->singleton(OrchestratorConfig::class, function ($app) {
            $config = $app['config']['orchestrator'] ?? [];

            return new OrchestratorConfig(is_array($config) ? $config : []);
        });

        $this->app->singleton(ModuleManifest::class, function ($app) {
            return new ModuleManifest($app->make(OrchestratorConfig::class)->manifestPath());
        });

        $this->app->singleton(ModuleDiscovery::class, function ($app) {
            return new ModuleDiscovery($app->make(OrchestratorConfig::class));
        });

        $this->app->singleton(ModuleRegistry::class, function ($app) {
            return new ModuleRegistry(
                $app->make(OrchestratorConfig::class),
                $app->make(ModuleDiscovery::class),
                $app->make(ModuleManifest::class)
            );
        });

        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager(null, $app->make(ModuleRegistry::class), $app);
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListModulesCommand::class,
                EnableModuleCommand::class,
                DisableModuleCommand::class,
                ReloadModulesCommand::class,
            ]);
        }

        $manager = $this->app->make(ModuleManager::class);
        $manager->setApplication($this->app);
        $manager->registerEnabledModules($this->app);
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
