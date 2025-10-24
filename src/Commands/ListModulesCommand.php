<?php

namespace Glugox\Orchestrator\Commands;

use Glugox\Orchestrator\ModuleManager;
use Illuminate\Console\Command;

class ListModulesCommand extends Command
{
    protected $signature = 'modules:list';

    protected $description = 'Display the discovered modules and their status.';

    public function handle(ModuleManager $modules): int
    {
        $rows = $modules->all()->map(function ($module) {
            return [
                'id' => $module->id(),
                'name' => $module->name(),
                'version' => $module->version(),
                'installed' => $module->isInstalled() ? 'yes' : 'no',
                'enabled' => $module->isEnabled() ? 'yes' : 'no',
                'providers' => implode(PHP_EOL, $module->providers()),
            ];
        });

        if ($rows->isEmpty()) {
            $this->info('No modules have been discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Version', 'Installed', 'Enabled', 'Providers'],
            $rows->map(fn ($row) => array_values($row))->all()
        );

        return self::SUCCESS;
    }
}
