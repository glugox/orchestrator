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
        $id = (string) $this->argument('module');
        $modules->reload();

        // Find all available modules specs from ./specs/*.json
        if (empty($id)) {
            $this->info('Building all modules...');

            foreach ($modules->specs() as $spec) {
                $this->info(sprintf('Building module [%s]...', $spec->toString()));
                // magic:build --package-path ./modules/glugox/module-a --package-name glugox/module-a --package-namespace Glugox\\ModuleA --starter orchestrator
                $this->call('magic:build', [
                    '--package-path' => $modules->modulesPath().'/'.$spec->id(),
                    '--package-name' => $spec->id(),
                    '--package-namespace' => $spec->namespace(),
                    '--config' => $spec->configPath(),
                ]);
            }
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
