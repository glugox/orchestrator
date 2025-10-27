<?php

namespace Tests\Fixtures;

use Glugox\Orchestrator\Services\ModuleInstaller;

class FakeModuleInstaller extends ModuleInstaller
{
    /**
     * @var array<int, array<int, string>>
     */
    public array $commands = [];

    protected function runComposerCommand(array $args): void
    {
        $this->commands[] = $args;
    }
}
