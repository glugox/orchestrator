# ![logo.svg](https://raw.githubusercontent.com/glugox/cloud/main/public/logo.svg) Orchestrator


## Introduction

The `glugox/orchestrator` package is the **runtime manager** for modules in a Laravel application. It discovers, loads, enables, disables, and manages modules that are built on top of `glugox/module`. It ensures that the application remains modular, scalable, and easy to maintain.

This package provides the orchestration layer that ties modules together into a unified Laravel application.

---

## Key Concepts

### 1. Module Discovery

* Orchestrator scans for modules via:

    * `composer.json` metadata
    * `modules/` directory
    * Manifest files (`manifest.json`)
* Discovered modules are registered into the Laravel application lifecycle.

### 2. Module Lifecycle

* Modules can be:

    * **Enabled** → Service provider is registered, routes, migrations, and assets loaded.
    * **Disabled** → Skipped during boot.
    * **Removed** → Deleted/uninstalled from app.

### 3. Integration with `glugox/module`

* Relies on contracts like `ModuleContract`, `HasRoutes`, `HasMigrations`, etc.
* Uses `ModuleManifest` to extract module metadata.
* Loads `ModuleServiceProvider` into Laravel.

### 4. Developer Tools

* Artisan commands for managing modules.
* APIs to interact with orchestrator programmatically.

---

## Package Features

* **Module Management Commands**:

    * `php artisan orchestrator:modules:list` → lists all modules with status.
    * `php artisan orchestrator:modules:enable {id}` → enables a module.
    * `php artisan orchestrator:modules:disable {id}` → disables a module.
    * `php artisan orchestrator:modules:reload` → rescans and reloads all modules.

* **Configurable Module Paths** → supports custom module directories.

* **Dependency Management** → ensures that dependent modules are enabled first.

* **Version Awareness** → can detect and report mismatches between module versions.

---

## Package Structure

```
glugox/orchestrator/
├── src/
│   ├── Commands/
│   │   ├── ListModulesCommand.php
│   │   ├── EnableModuleCommand.php
│   │   ├── DisableModuleCommand.php
│   │   └── ReloadModulesCommand.php
│   ├── Services/
│   │   ├── ModuleManager.php
│   │   └── ModuleRegistry.php
│   ├── Support/
│   │   ├── ModuleDiscovery.php
│   │   └── OrchestratorConfig.php
│   └── OrchestratorServiceProvider.php
└── composer.json
```

---

## Example Workflow

1. Install `glugox/orchestrator` in a Laravel app:

```bash
composer require glugox/orchestrator
```

2. Run module discovery:

```bash
php artisan orchestrator:modules:list
```

Output:

```
ID                Name      Version   Status
company/billing   Billing   1.0.0     Enabled
company/crm       CRM       1.0.0     Disabled
```

3. Enable a module:

```bash
php artisan orchestrator:modules:enable company/crm
```

4. Orchestrator automatically:

    * Loads `CRMModule` class (extending `Module` from `glugox/module`).
    * Registers its service provider.
    * Loads routes, migrations, and assets if provided.

---

## Example API Usage

```php
use Glugox\Orchestrator\Services\ModuleManager;

$manager = app(ModuleManager::class);

// List all modules
$modules = $manager->all();

// Enable a module
$manager->enable('company/billing');

// Disable a module
$manager->disable('company/crm');
```

---

## How It Fits in the Ecosystem

* **`glugox/module`** provides the contracts and abstractions orchestrator enforces.
* **`glugox/module-generator`** produces modules that orchestrator can discover and manage.
* **Main Laravel App** → orchestrator integrates modules seamlessly into the app lifecycle.

---

## Benefits

* **Centralized Management** → All modules are controlled via a single tool.
* **Scalability** → Large Laravel apps remain organized with plug-and-play modules.
* **Flexibility** → Supports both local modules (`/modules`) and composer-installed ones.
* **Control** → Modules can be enabled/disabled without code changes.

---

## Next Steps

* Add GUI/Dashboard integration for module management.
* Implement dependency resolution between modules.
* Add support for remote module registries.
* Provide hooks for module lifecycle events (before enable, after disable, etc.).


---
## How it works in the greater ecosystem
# ![logo.svg](https://raw.githubusercontent.com/glugox/cloud/main/public/logo.svg) Cloud


Build and deploy modular Laravel applications with ease.

### Glugox Modular Ecosystem

## Introduction

The Glugox Modular Ecosystem enables building **large, modular Laravel applications** with minimal code in the main Laravel folder structure. It is composed of three tightly integrated packages:

* **[`glugox/module`](https://github.com/glugox/module)** → defines contracts, abstractions, and base classes for modules.
* **[`glugox/module-generator`](https://github.com/glugox/module-generator)** → scaffolds modules from JSON/YAML specifications.
* **[`glugox/orchestrator`](https://github.com/glugox/orchestrator)** → manages modules (discovery, enable/disable, lifecycle) inside a Laravel application.

This ecosystem allows developers to focus on high-level specifications while the tooling handles scaffolding and orchestration.

---

## Packages Overview

### 1. glugox/module (Foundation)

* Defines contracts (`ModuleContract`, `HasRoutes`, `HasMigrations`, etc.).
* Provides the `Module` base class.
* Implements `ModuleManifest` for module metadata.
* Ensures all modules are consistent and self-describing.

### 2. glugox/module-generator (Factory)

* Reads module specs (`/specs/*.json` or `.yaml`).
* Generates fully structured modules under `/modules/{Vendor}/{Name}`.
* Produces backend (models, migrations, routes) and frontend (Vue/Inertia) scaffolding.
* Ensures compliance with `glugox/module` contracts.

### 3. glugox/orchestrator (Conductor)

* Discovers installed modules.
* Loads their manifests and registers service providers.
* Provides artisan commands to enable, disable, and list modules.
* Controls module lifecycle at runtime.

---

## Example Workflow

1. **Define a Spec**

```json
{
  "schemaVersion": "1.0.0",
  "module": {
    "id": "company/billing",
    "name": "Billing",
    "namespace": "Company\\Billing",
    "description": "Invoices and payments",
    "capabilities": ["http:web", "http:api"]
  },
  "models": [
    {
      "name": "Invoice",
      "fields": [
        { "name": "number", "type": "string" },
        { "name": "amount", "type": "decimal" },
        { "name": "status", "type": "enum:pending,paid,cancelled" }
      ]
    }
  ]
}
```

2. **Generate Module**

```bash
php artisan module:generate billing
```

3. **List Modules**

```bash
php artisan orchestrator:modules:list
```

4. **Enable Module**

```bash
php artisan orchestrator:modules:enable company/billing
```

5. **Run Application**

* Orchestrator loads the Billing module.
* Service provider, migrations, routes, and assets are available.

---

## Directory Layout

```
myapp/
├── specs/
│   ├── billing.json
│   └── crm.json
├── modules/
│   ├── Company/
│   │   └── Billing/
│   └── Company/
│       └── Crm/
├── vendor/
│   ├── glugox/module
│   ├── glugox/module-generator
│   └── glugox/orchestrator
└── composer.json
```

---

## Responsibilities

### For Module Developers

* Extend `Module` class from `glugox/module`.
* Provide routes, migrations, and service provider.
* Follow manifest structure.

### For Generator Developers

* Maintain parsers and templates.
* Ensure generator output strictly follows `glugox/module`.
* Extend with frontend and testing scaffolds.

### For Orchestrator Developers

* Maintain discovery and lifecycle logic.
* Implement artisan commands.
* Ensure safe integration into Laravel boot cycle.

### For Main App Developers

* Write specs in `/specs`.
* Generate modules with `module-generator`.
* Use `orchestrator` commands to enable/disable modules.
* Keep the main app minimal.

---

## Benefits

* **Separation of Concerns** → contracts, generation, and orchestration are independent.
* **Consistency** → all modules follow the same structure.
* **Scalability** → supports very large Laravel applications.
* **Developer Velocity** → new features added rapidly via specs.

---

## Next Steps

* Provide demo app (`glugox/app-demo`).
* Add CI/CD pipelines for regeneration and tests.
* Extend generator for advanced cases (multi-tenancy, permissions).
* Build admin dashboard for visual module management.

---

## Summary

The Glugox ecosystem standardizes how modules are **defined**, **generated**, and **managed** in Laravel:

* `glugox/module`: the **foundation**.
* `glugox/module-generator`: the **factory**.
* `glugox/orchestrator`: the **conductor**.

Together, they enable highly modular, reusable, and maintainable Laravel applications.
