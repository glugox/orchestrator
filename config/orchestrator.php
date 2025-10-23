<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | By default the orchestrator resolves all relative paths using the Laravel
    | application's base path. When the helper is not available (for example
    | when running inside tests without a full application container) we fall
    | back to the current working directory.
    */
    'base_path' => function_exists('base_path') ? base_path() : getcwd(),

    /*
    |--------------------------------------------------------------------------
    | Manifest Path
    |--------------------------------------------------------------------------
    |
    | The manifest file caches the discovered modules and their state. It is
    | stored inside the bootstrap cache directory by default.
    */
    'manifest_path' => 'bootstrap/cache/modules.php',

    /*
    |--------------------------------------------------------------------------
    | Composer installed.json Path
    |--------------------------------------------------------------------------
    |
    | Modules are primarily discovered through Composer metadata. You may
    | customise the location of the installed.json file if your application
    | keeps it somewhere else.
    */
    'installed_path' => 'vendor/composer/installed.json',

    /*
    |--------------------------------------------------------------------------
    | Additional Module Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Some packages may ship a standalone module.json file outside of Composer.
    | The orchestrator will scan the glob patterns listed here and merge any
    | discovered modules into the manifest.
    */
    'module_json_paths' => [
        'vendor/*/*/module.json',
        'modules/*/module.json',
        'packages/*/*/module.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default State
    |--------------------------------------------------------------------------
    |
    | When a new module is discovered the orchestrator will mark it as installed
    | and enabled according to these toggles unless the manifest already
    | contains explicit state for the module.
    */
    'auto_install' => true,
    'auto_enable' => true,
];
