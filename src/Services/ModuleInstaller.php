<?php

namespace Glugox\Orchestrator\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use RuntimeException;

class ModuleInstaller
{
    protected string $composerJsonPath;

    public function __construct(string $composerJsonPath = null)
    {
        $this->composerJsonPath = $composerJsonPath ?? base_path('composer.json');
    }

    /**
     * Install a module either from local path (modules/Vendor/Name) or remote (Packagist/GitHub).
     *
     * @param  string       $packageName   e.g. "glugox/crm"
     * @param  string|null  $localPath     e.g. "modules/Glugox/Crm"
     * @param  string       $version       e.g. "@dev" or "^1.0"
     * @return void
     */
    public function install(string $packageName, ?string $localPath = null, string $version = '@dev'): void
    {
        if ($localPath && is_dir($localPath)) {
            $this->ensureLocalRepository($localPath);
        }

        $this->requirePackage($packageName, $version);
        $this->dumpAutoload();
    }

    /**
     * Ensure a local module is registered as a path repository in composer.json
     */
    protected function ensureLocalRepository(string $localPath): void
    {
        if (! file_exists($this->composerJsonPath)) {
            throw new RuntimeException("composer.json not found at {$this->composerJsonPath}");
        }

        $composer = json_decode(file_get_contents($this->composerJsonPath), true) ?? [];
        $repositories = $composer['repositories'] ?? [];

        $repoEntry = [
            'type' => 'path',
            'url' => $localPath,
            'options' => ['symlink' => true],
        ];

        // Already present?
        foreach ($repositories as $repo) {
            if (($repo['type'] ?? '') === 'path' && ($repo['url'] ?? '') === $localPath) {
                return;
            }
        }

        $repositories[] = $repoEntry;
        $composer['repositories'] = $repositories;
        $composer['minimum-stability'] = $composer['minimum-stability'] ?? 'dev';
        $composer['prefer-stable'] = $composer['prefer-stable'] ?? true;

        file_put_contents(
            $this->composerJsonPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Log::info("Added local repository for {$localPath} to composer.json");
    }

    /**
     * Run composer require to install a package.
     */
    protected function requirePackage(string $packageName, string $version): void
    {
        $args = ["require", "{$packageName}:{$version}"];

        $this->runComposerCommand($args);

        Log::info("Required package {$packageName} ({$version})");
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
}
