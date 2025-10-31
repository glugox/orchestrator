<?php

use Illuminate\Filesystem\Filesystem;

it('bootstraps orchestrator by preparing configuration and directories', function () {
    $filesystem = new Filesystem();
    $sandbox = createSandbox();

    try {
        $this->app->setBasePath($sandbox);
        $this->app->instance('path.config', $sandbox.'/config');
        $this->app['config']->set('orchestrator', orchestratorConfig($sandbox));

        $this->artisan('orchestrator:boot')
            ->expectsOutput('Discovered 0 module(s).')
            ->expectsOutputToContain('Orchestrator bootstrapped successfully.')
            ->assertExitCode(0);

        expect($filesystem->exists($sandbox.'/config/orchestrator.php'))->toBeTrue();
        expect($filesystem->isDirectory($sandbox.'/modules'))->toBeTrue();
        expect($filesystem->isDirectory($sandbox.'/specs/modules'))->toBeTrue();
        expect($filesystem->exists($sandbox.'/bootstrap/cache/modules.php'))->toBeTrue();
    } finally {
        cleanupSandbox($sandbox);
    }
});

it('respects existing configuration unless forced', function () {
    $filesystem = new Filesystem();
    $sandbox = createSandbox();

    try {
        $this->app->setBasePath($sandbox);
        $this->app->instance('path.config', $sandbox.'/config');

        $filesystem->ensureDirectoryExists($sandbox.'/config');
        file_put_contents($sandbox.'/config/orchestrator.php', "<?php\n\nreturn ['custom' => true];\n");

        $this->app['config']->set('orchestrator', orchestratorConfig($sandbox, [
            'modules_default_path' => 'custom-modules',
        ]));

        $this->artisan('orchestrator:boot --no-discover')
            ->expectsOutput('Orchestrator bootstrapped successfully.')
            ->assertExitCode(0);

        expect(file_get_contents($sandbox.'/config/orchestrator.php'))
            ->toContain("'custom' => true");
        expect($filesystem->isDirectory($sandbox.'/custom-modules'))->toBeTrue();

        $this->artisan('orchestrator:boot --force --no-discover')
            ->expectsOutputToContain('Configuration file published')
            ->assertExitCode(0);

        expect(file_get_contents($sandbox.'/config/orchestrator.php'))
            ->not->toContain("'custom' => true");
    } finally {
        cleanupSandbox($sandbox);
    }
});
