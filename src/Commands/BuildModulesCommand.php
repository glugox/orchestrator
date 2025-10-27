<?php

namespace Glugox\Orchestrator\Commands;

use Glugox\Orchestrator\ModuleManager;
use Glugox\Orchestrator\SpecDescriptor;
use Illuminate\Console\Command;
use Throwable;

class BuildModulesCommand extends Command
{
    protected $signature = 'orchestrator:build {module? : The module identifier}';

    protected $description = 'Builds a module by json schema.';

    public function handle(ModuleManager $modules): int
    {
        $specsToBuild = [];
        $id = (string) $this->argument('module');
        $modules->reload();

        // Find all available modules specs from ./specs/*.json
        foreach ($modules->specs() as $spec) {
            // Check passed id
            if (! empty($id) && $spec->id() !== $id) {
                continue;
            }
            $specsToBuild[] = $spec;
        }

        if ($specsToBuild === []) {
            if ($id !== '') {
                $this->warn(sprintf('No module spec registered for [%s].', $id));
            } else {
                $this->warn('No module specs were discovered.');
            }

            return self::FAILURE;
        }

        $application = $this->getApplication();

        if ($application === null || ! $application->has('magic:build')) {
            $this->error('The "magic:build" command is not available. Please install the module generator package.');

            return self::FAILURE;
        }

        foreach ($specsToBuild as $spec) {
            $this->info(sprintf('Building module [%s]...', $spec->id()));

            try {
                $exitCode = $this->call('magic:build', [
                    '--package-path' => $this->resolvePackagePath($modules, $spec),
                    '--package-name' => $spec->id(),
                    '--package-namespace' => $spec->namespace(),
                    '--config' => $spec->configPath(),
                ]);
            } catch (Throwable $exception) {
                $this->error(sprintf('Failed to build module [%s]: %s', $spec->id(), $exception->getMessage()));

                return self::FAILURE;
            }

            if ($exitCode !== 0) {
                $this->error(sprintf('magic:build exited with code %d for module [%s].', $exitCode, $spec->id()));

                return self::FAILURE;
            }

            $this->info(sprintf('Module [%s] built.', $spec->id()));
        }

        return self::SUCCESS;
    }

    protected function resolvePackagePath(ModuleManager $modules, SpecDescriptor $spec): string
    {
        // Derive the PSR-4 compliant directory that matches the module namespace.
        $basePath = rtrim($modules->modulesPath(), DIRECTORY_SEPARATOR);
        $namespacePath = trim(str_replace('\\', DIRECTORY_SEPARATOR, $spec->namespace()), DIRECTORY_SEPARATOR);

        if ($namespacePath === '') {
            $namespacePath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $spec->id()), DIRECTORY_SEPARATOR);
        }

        return $basePath.DIRECTORY_SEPARATOR.$namespacePath;
    }
}
