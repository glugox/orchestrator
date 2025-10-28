#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
APP_DIR="$REPO_ROOT/laravel-app"

if [ -d "$APP_DIR" ]; then
    rm -rf "$APP_DIR"
fi

cd "$REPO_ROOT"

composer create-project laravel/laravel laravel-app

cd "$APP_DIR"

composer require glugox/orchestrator:@dev

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

