<?php

namespace Glugox\Orchestrator;

use Illuminate\Support\ServiceProvider;

class OrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('orchestrator', function () {
            return new OrchestratorManager();
        });
    }

    public function boot(): void
    {
        $this->publishes([
        __DIR__ . '/../config/orchestrator.php' => config_path('orchestrator.php'),
    ], 'config');
    }
}
