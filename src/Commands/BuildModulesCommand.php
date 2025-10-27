<?php

namespace Glugox\Orchestrator\Commands;

use Glugox\Orchestrator\ModuleManager;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuildModulesCommand extends Command
{
    protected $signature = 'orchestrator:build {module? : The module identifier}';

    protected $description = 'Builds a module by json schema.';

    public function handle(ModuleManager $modules): int
    {
        $modulesSpecs = [];
        $id = (string) $this->argument('module');
        $modules->reload();

        // Find all available modules specs from ./specs/*.json
        foreach ($modules->specs() as $spec) {
            // Check passed id
            if (!empty($id) && $spec->id() !== $id) {
                continue;
            }
            $modulesSpecs[] = $spec->id();
        }

        foreach ($modulesSpecs as $spec) {
            $this->info(sprintf('Building module [%s]...', $spec->toString()));
            // magic:build --package-path ./modules/glugox/module-a --package-name glugox/module-a --package-namespace Glugox\\ModuleA --starter orchestrator
            $this->call('magic:build', [
                '--package-path' => $modules->modulesPath().'/'.$spec->id(),
                '--package-name' => $spec->id(),
                '--package-namespace' => $spec->namespace(),
                '--config' => $spec->configPath(),
            ]);
        }

        try {
            // Enable the module
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Module [%s] built.', $id));

        return self::SUCCESS;
    }
}
