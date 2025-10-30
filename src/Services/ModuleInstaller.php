<?php

namespace Glugox\Orchestrator\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use RuntimeException;

class ModuleInstaller
{
    private const DEFAULT_MODULE_VERSION = '^1.0';
    private const DEV_VERSION_CONSTRAINT = '@dev';

    protected string $composerJsonPath;
    protected string $defaultModuleVersion;

    public function __construct(?string $composerJsonPath = null, ?string $defaultModuleVersion = null)
    {
        $this->composerJsonPath = $composerJsonPath ?? base_path('composer.json');
        $this->defaultModuleVersion = $defaultModuleVersion ?? $this->resolveDefaultModuleVersion();
    }

    /**
     * Install a module either from local path (modules/Vendor/Name) or remote (Packagist/GitHub).
     *
     * @param  string       $packageName   e.g. "glugox/crm"
     * @param  string|null  $localPath     e.g. "modules/Glugox/Crm"
     * @param  string|null  $version       e.g. "@dev" or "^1.0"
     * @return void
     */
    public function install(string $packageName, ?string $localPath = null, ?string $version = null): void
    {
        $alreadyRequired = $this->isPackageAlreadyRequired($packageName);
        $repositoryUpdated = false;

        if ($localPath && is_dir($localPath)) {
            $repositoryUpdated = $this->ensureLocalRepository($localPath);
        }

        if ($alreadyRequired) {
            Log::info("Package {$packageName} already required; skipping composer require.");

            if ($repositoryUpdated) {
                $this->dumpAutoload();
            }

            return;
        }

        $this->requirePackage($packageName, $version ?? $this->defaultModuleVersion);
        $this->dumpAutoload();
    }

    /**
     * Ensure a local module is registered as a path repository in composer.json
     */
    protected function ensureLocalRepository(string $localPath): bool
    {
        $composer = $this->loadComposerJson();
        $repositories = $composer['repositories'] ?? [];

        $repoEntry = [
            'type' => 'path',
            'url' => $localPath,
            'options' => ['symlink' => true],
        ];

        // Already present?
        foreach ($repositories as $repo) {
            if (($repo['type'] ?? '') === 'path' && ($repo['url'] ?? '') === $localPath) {
                return false;
            }
        }

        $repositories[] = $repoEntry;
        $composer['repositories'] = $repositories;
        $composer['minimum-stability'] = $composer['minimum-stability'] ?? 'dev';
        $composer['prefer-stable'] = $composer['prefer-stable'] ?? true;

        $this->saveComposerJson($composer);

        Log::info("Added local repository for {$localPath} to composer.json");

        return true;
    }

    /**
     * Determine whether the package already exists in composer.json.
     */
    protected function isPackageAlreadyRequired(string $packageName): bool
    {
        if (! file_exists($this->composerJsonPath)) {
            return false;
        }

        $composer = $this->loadComposerJson();
        $requires = $composer['require'] ?? [];
        $devRequires = $composer['require-dev'] ?? [];

        return isset($requires[$packageName]) || isset($devRequires[$packageName]);
    }

    /**
     * Run composer require to install a package.
     */
    protected function requirePackage(string $packageName, string $version): void
    {
        $args = ["require", "{$packageName}:{$version}"];

        try {
            $this->runComposerCommand($args);
            Log::info("Required package {$packageName} ({$version})");

            return;
        } catch (RuntimeException $exception) {
            if (! $this->shouldRetryWithDevVersion($version, $exception)) {
                throw $exception;
            }

            $fallbackVersion = self::DEV_VERSION_CONSTRAINT;
            Log::warning(
                "Composer could not satisfy {$packageName}:{$version}; retrying with {$fallbackVersion}."
            );

            $this->runComposerCommand(["require", "{$packageName}:{$fallbackVersion}"]);
            Log::info("Required package {$packageName} ({$fallbackVersion})");
        }
    }

    /**
     * Run composer dump-autoload to refresh autoloaders.
     */
    protected function dumpAutoload(): void
    {
        $this->runComposerCommand(["dump-autoload"]);
        Log::info("Composer autoload refreshed");
    }

    /**
     * Helper: run composer safely and stream output.
     */
    protected function runComposerCommand(array $args): void
    {
        $cmd = array_merge(['composer'], $args);

        $process = new Process($cmd, base_path());
        $process->setTimeout(300);

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Composer command failed: " . $process->getErrorOutput());
        }
    }

    protected function shouldRetryWithDevVersion(string $version, RuntimeException $exception): bool
    {
        if ($version === self::DEV_VERSION_CONSTRAINT) {
            return false;
        }

        $message = $exception->getMessage();

        if ($message === '') {
            return false;
        }

        return Str::contains($message, [
            'does not match the constraint',
            'could not be found in any version',
        ]);
    }

    protected function resolveDefaultModuleVersion(): string
    {
        if (function_exists('config')) {
            $configured = config('orchestrator.modules_default_version');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return self::DEFAULT_MODULE_VERSION;
    }

    /**
     * Load composer.json from disk.
     */
    protected function loadComposerJson(): array
    {
        if (! file_exists($this->composerJsonPath)) {
            throw new RuntimeException("composer.json not found at {$this->composerJsonPath}");
        }

        $decoded = json_decode(file_get_contents($this->composerJsonPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist composer.json to disk.
     */
    protected function saveComposerJson(array $composer): void
    {
        file_put_contents(
            $this->composerJsonPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

