#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/laravel-app"

DEFAULT_ORCHESTRATOR_PATH="/Users/ervin/Code/github.com/glugox/orchestrator"
LOCAL_ORCHESTRATOR_PATH="${ORCHESTRATOR_PATH:-$DEFAULT_ORCHESTRATOR_PATH}"

if [ ! -d "$LOCAL_ORCHESTRATOR_PATH" ]; then
    POTENTIAL_REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
    if [ -f "$POTENTIAL_REPO_ROOT/composer.json" ]; then
        LOCAL_ORCHESTRATOR_PATH="$POTENTIAL_REPO_ROOT"
    else
        echo "Unable to locate local orchestrator package. Set ORCHESTRATOR_PATH to the package root." >&2
        exit 1
    fi
fi

if [ -d "$APP_DIR" ]; then
    rm -rf "$APP_DIR"
fi

composer create-project laravel/laravel "$APP_DIR"

# Sleep to allow filesystem to settle (especially on Windows)
# display messages before proceeding
printf "Waiting for filesystem to settle...\n"

sleep 2


cd "$APP_DIR"

composer config minimum-stability dev
composer config prefer-stable false

export LOCAL_ORCHESTRATOR_PATH
php <<'PHP'
<?php
$path = getenv('LOCAL_ORCHESTRATOR_PATH');
$composerFile = __DIR__ . '/composer.json';
$contents = json_decode(file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
$contents['repositories']['glugox-orchestrator-local'] = [
    'type' => 'path',
    'url' => $path,
    'options' => ['symlink' => true],
];
file_put_contents(
    $composerFile,
    json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);
PHP

composer require glugox/orchestrator:@dev
composer require --dev pestphp/pest --with-all-dependencies

php artisan vendor:publish --tag=orchestrator-config --force

if [ -L "$APP_DIR/specs" ] || [ -e "$APP_DIR/specs" ]; then
    rm -rf "$APP_DIR/specs"
fi
ln -sfn "$SCRIPT_DIR/specs" "$APP_DIR/specs"

mkdir -p tests/Feature

cat <<'PHP' > tests/Feature/BlogTest.php
<?php

use function Pest\Laravel\get;

it('loads the blog posts index', function () {
    get('/blog/posts')
        ->assertOk()
        ->assertSee('Posts');
});
PHP

cat <<'PHP' > tests/Feature/CrmTest.php
<?php

use function Pest\Laravel\get;

it('loads the CRM customers index', function () {
    get('/crm/customers')
        ->assertOk()
        ->assertSee('Customers');
});
PHP

