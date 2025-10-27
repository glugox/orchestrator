<?php

use Tests\Fixtures\FakeModuleInstaller;

it('skips composer require when package already exists', function () {
    $sandbox = createSandbox();

    try {
        $composerPath = $sandbox.'/composer.json';
        $modulePath = $sandbox.'/modules/Vendor/Foo';

        mkdir($modulePath, 0777, true);

        file_put_contents(
            $composerPath,
            json_encode([
                'require' => ['vendor/foo-module' => '^1.0'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $installer = new FakeModuleInstaller($composerPath);
        $installer->install('vendor/foo-module', $modulePath);

        expect($installer->commands)->toHaveCount(1)
            ->and($installer->commands[0])->toBe(['dump-autoload']);

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['repositories'][0]['url'] ?? null)->toBe($modulePath);

        $installer->commands = [];
        $installer->install('vendor/foo-module', $modulePath);

        expect($installer->commands)->toBe([]);
    } finally {
        cleanupSandbox($sandbox);
    }
});

it('runs composer require for new packages', function () {
    $sandbox = createSandbox();

    try {
        $composerPath = $sandbox.'/composer.json';
        $modulePath = $sandbox.'/modules/Vendor/Foo';

        mkdir($modulePath, 0777, true);

        file_put_contents(
            $composerPath,
            json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $installer = new FakeModuleInstaller($composerPath);
        $installer->install('vendor/foo-module', $modulePath);

        expect($installer->commands)->toMatchArray([
            ['require', 'vendor/foo-module:@dev'],
            ['dump-autoload'],
        ]);

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['repositories'][0]['url'] ?? null)->toBe($modulePath)
            ->and($composer['minimum-stability'])->toBe('dev')
            ->and($composer['prefer-stable'])->toBeTrue();
    } finally {
        cleanupSandbox($sandbox);
    }
});
