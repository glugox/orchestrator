#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/laravel-app"

if [ -d "$APP_DIR" ]; then
    rm -rf "$APP_DIR"
fi

composer create-project laravel/laravel "$APP_DIR"

cd "$APP_DIR"

composer config minimum-stability dev

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

