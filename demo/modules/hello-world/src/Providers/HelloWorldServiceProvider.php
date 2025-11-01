<?php

namespace Demo\HelloWorld\Providers;

use Glugox\Orchestrator\Facades\Modules;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HelloWorldServiceProvider extends ServiceProvider
{
    protected string $moduleId = 'demo/hello-world';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/module.php', 'modules.'.$this->moduleId);
    }

    public function boot(): void
    {
        if (! Modules::enabled($this->moduleId)) {
            return;
        }

        $this->mapRoutes();
    }

    protected function mapRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->name('demo.hello-world.')
            ->group(__DIR__.'/../../routes/api.php');
    }
}
