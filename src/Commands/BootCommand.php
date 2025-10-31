<?php

namespace Glugox\Orchestrator\Commands;

use Glugox\Orchestrator\ModuleManager;
use Glugox\Orchestrator\Support\OrchestratorConfig;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class BootCommand extends Command
{
    protected $signature = 'orchestrator:boot {--force : Overwrite the published configuration file if it already exists} {--no-discover : Skip module discovery and manifest generation}';

    protected $description = 'Bootstrap the application to work with Glugox Orchestrator.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(ModuleManager $modules, OrchestratorConfig $config): int
    {
        $configPublished = $this->publishConfiguration((bool) $this->option('force'));
        $this->ensureRuntimeDirectories($config);

        if (! $this->option('no-discover')) {
            $discovered = $modules->discover();
            $modules->registerEnabledModules($this->laravel);
            $this->info(sprintf('Discovered %d module(s).', count($discovered)));
        }

        if ($configPublished) {
            $this->info('Configuration file published to '. $this->configPath());
        }

        $this->info('Orchestrator bootstrapped successfully.');

        return self::SUCCESS;
    }

    protected function publishConfiguration(bool $force): bool
    {
        $target = $this->configPath();
        $source = __DIR__.'/../../config/orchestrator.php';

        if ($this->files->exists($target) && ! $force) {
            return false;
        }

        $this->ensureDirectory(dirname($target));
        $this->files->copy($source, $target);
        $this->laravel['config']->set('orchestrator', require $target);

        return true;
    }

    protected function ensureRuntimeDirectories(OrchestratorConfig $config): void
    {
        $this->ensureDirectory($config->modulesPath());
        $this->ensureDirectory(dirname($config->manifestPath()));
        $this->ensureDirectory($config->moduleSpecsPath());
    }

    protected function ensureDirectory(string $path): void
    {
        if ($this->files->isDirectory($path)) {
            return;
        }

        $this->files->makeDirectory($path, 0777, true);
    }

    protected function configPath(): string
    {
        if (method_exists($this->laravel, 'configPath')) {
            return $this->laravel->configPath('orchestrator.php');
        }

        return $this->laravel->basePath('config/orchestrator.php');
    }
}
