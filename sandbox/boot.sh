#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEMO_DIR="$REPO_ROOT/demo"
APP_DIR="$DEMO_DIR/laravel-app"
SPECS_DIR="$SCRIPT_DIR/specs"
HELLO_WORLD_DIR="$DEMO_DIR/modules/hello-world"

if [ ! -d "$DEMO_DIR" ]; then
    echo "❌ Demo directory not found at $DEMO_DIR" >&2
    exit 1
fi

if [ ! -d "$HELLO_WORLD_DIR" ]; then
    echo "❌ Hello World module not found at $HELLO_WORLD_DIR" >&2
    exit 1
fi

rm -rf "$APP_DIR"

composer create-project laravel/laravel "$APP_DIR"

printf "Waiting for filesystem to settle...\n"
sleep 2

cd "$APP_DIR"

composer config minimum-stability dev
composer config prefer-stable true

COMPOSER_FILE="$APP_DIR/composer.json"
export COMPOSER_FILE

resolve_optional_path() {
    for candidate in "$@"; do
        if [ -n "$candidate" ] && [ -d "$candidate" ]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    return 0
}

add_path_repository() {
    local repo_name="$1"
    local repo_path="$2"

    if [ ! -d "$repo_path" ]; then
        echo "⚠️  Skipping $repo_name because path $repo_path does not exist." >&2
        return
    fi

    REPO_NAME="$repo_name" REPO_PATH="$repo_path" php <<'PHP'
    $composerFile = getenv('COMPOSER_FILE');
    $repoName = getenv('REPO_NAME');
    $repoPath = getenv('REPO_PATH');

    $contents = json_decode(file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
    $contents['repositories'] = $contents['repositories'] ?? [];
    $contents['repositories'][$repoName] = [
        'type' => 'path',
        'url' => $repoPath,
        'options' => ['symlink' => true],
    ];

    file_put_contents(
        $composerFile,
        json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
PHP
}

DEFAULT_ORCHESTRATOR_PATH="$REPO_ROOT"
DEFAULT_MODULE_PATH="$(resolve_optional_path "${MODULE_PATH:-}" "$REPO_ROOT/../module" "$REPO_ROOT/../packages/module" "$REPO_ROOT/../glugox-module")"
DEFAULT_MAGIC_PATH="$(resolve_optional_path "${MAGIC_PATH:-}" "$REPO_ROOT/../magic" "$REPO_ROOT/../packages/magic" "$REPO_ROOT/../glugox-magic")"

add_path_repository glugox-orchestrator-local "$DEFAULT_ORCHESTRATOR_PATH"

if [ -n "$DEFAULT_MODULE_PATH" ]; then
    add_path_repository glugox-module-local "$DEFAULT_MODULE_PATH"
fi

if [ -n "$DEFAULT_MAGIC_PATH" ]; then
    add_path_repository glugox-magic-local "$DEFAULT_MAGIC_PATH"
fi

add_path_repository demo-hello-world "$HELLO_WORLD_DIR"

printf "Waiting before requiring packages...\n"
sleep 2

composer require glugox/orchestrator:@dev
composer require demo/hello-world:@dev
composer require --dev pestphp/pest --with-all-dependencies --prefer-stable

php artisan vendor:publish --tag=orchestrator-config --force

if [ -L "$APP_DIR/specs" ] || [ -e "$APP_DIR/specs" ]; then
    rm -rf "$APP_DIR/specs"
fi
ln -sfn "$SPECS_DIR" "$APP_DIR/specs"

mkdir -p modules/hello-world
if command -v rsync >/dev/null 2>&1; then
    rsync -a "$HELLO_WORLD_DIR/" "$APP_DIR/modules/hello-world/"
else
    cp -a "$HELLO_WORLD_DIR/." "$APP_DIR/modules/hello-world/"
fi

php artisan orchestrator:build
php artisan orchestrator:install demo/hello-world

printf "Hello World module installed. Try hitting /api/hello-world after starting the Laravel server.\n"
