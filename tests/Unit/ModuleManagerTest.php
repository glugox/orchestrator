<?php

use Glugox\Orchestrator\ModuleManager;

it('discovers modules from composer metadata and module json files', function () {
    $sandbox = createSandbox();

    try {
        populateSandbox($sandbox);

        $manager = new ModuleManager(orchestratorConfig($sandbox));

        expect($manager->all())->toHaveCount(2);

        $composerModule = $manager->module('vendor/foo-module');
        expect($composerModule->name())->toBe('Vendor Foo Module');
        expect($composerModule->version())->toBe('1.2.3');
        expect($composerModule->basePath())->toBe($sandbox.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'foo-module');
        expect($composerModule->isInstalled())->toBeTrue();
        expect($composerModule->isEnabled())->toBeTrue();
        expect($composerModule->providers())->toBe(['Vendor\\Foo\\ServiceProvider']);
        expect($composerModule->paths())
            ->toMatchArray([
                'routes' => 'routes/web.php',
                'migrations' => 'database/migrations',
            ]);

        $moduleJson = $manager->module('custom/module-json');
        expect($moduleJson->name())->toBe('Custom Module');
        expect($moduleJson->basePath())->toBe($sandbox.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'custom');
        expect($moduleJson->paths()['routes'])->toBe([
            'routes/api.php',
            'routes/web.php',
        ]);
        expect($moduleJson->providers())->toBe(['Custom\\Module\\Provider']);
    } finally {
        cleanupSandbox($sandbox);
    }
});

it('persists module state changes to the manifest', function () {
    $sandbox = createSandbox();

    try {
        populateSandbox($sandbox);

        $manager = new ModuleManager(orchestratorConfig($sandbox));

        $manager->disable('vendor/foo-module');
        expect($manager->isEnabled('vendor/foo-module'))->toBeFalse();
        expect(is_file($sandbox.'/bootstrap/cache/modules.php'))->toBeTrue();

        $reloaded = new ModuleManager(orchestratorConfig($sandbox));
        expect($reloaded->isCached())->toBeTrue();
        expect($reloaded->module('vendor/foo-module')->isEnabled())->toBeFalse();
        expect($reloaded->module('vendor/foo-module')->isInstalled())->toBeTrue();
    } finally {
        cleanupSandbox($sandbox);
    }
});

function createSandbox(): string
{
    $sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orch-'.uniqid('', true);
    mkdir($sandbox, 0777, true);

    return $sandbox;
}

function orchestratorConfig(string $basePath): array
{
    return [
        'base_path' => $basePath,
        'manifest_path' => 'bootstrap/cache/modules.php',
        'installed_path' => 'vendor/composer/installed.json',
        'module_json_paths' => ['modules/*/module.json'],
        'auto_install' => true,
        'auto_enable' => true,
    ];
}

function populateSandbox(string $basePath): void
{
    $composerDir = $basePath.'/vendor/composer';
    $modulesDir = $basePath.'/modules/custom';
    $cacheDir = $basePath.'/bootstrap/cache';
    $vendorModuleDir = $basePath.'/vendor/foo-module';

    foreach ([$composerDir, $modulesDir, $cacheDir, $vendorModuleDir] as $directory) {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    $installed = [
        'packages' => [
            [
                'name' => 'vendor/foo-module',
                'type' => 'laravel-module',
                'version' => '1.2.3',
                'install_path' => '../foo-module',
                'extra' => [
                    'laravel' => [
                        'providers' => ['Vendor\\Foo\\ServiceProvider'],
                    ],
                    'glugox-module' => [
                        'id' => 'vendor/foo-module',
                        'name' => 'Vendor Foo Module',
                        'version' => '1.2.3',
                        'routes' => 'routes/web.php',
                        'migrations' => 'database/migrations',
                        'capabilities' => ['api'],
                    ],
                ],
            ],
        ],
    ];

    file_put_contents(
        $composerDir.'/installed.json',
        json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $moduleJson = [
        'id' => 'custom/module-json',
        'name' => 'Custom Module',
        'version' => '0.1.0',
        'routes' => ['routes/api.php', 'routes/web.php'],
        'providers' => ['Custom\\Module\\Provider'],
    ];

    file_put_contents(
        $modulesDir.'/module.json',
        json_encode($moduleJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function cleanupSandbox(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $fileInfo) {
        if ($fileInfo->isDir()) {
            rmdir($fileInfo->getPathname());
        } else {
            unlink($fileInfo->getPathname());
        }
    }

    rmdir($directory);
}
