<?php

namespace Glugox\Orchestrator;

/**
 * Class OrchestratorManager
 *
 * Manages tracking of file additions, edits, deletions, and reverts.
 */
class OrchestratorManager
{
    protected array $changes = [
        'added' => [],
        'edited' => [],
        'deleted' => [],
    ];

    /**
     * Record a file addition.
     *
     * @param string $path The path of the added file.
     */
    public function add(string $path): void
    {
        $this->changes['added'][] = $path;
    }

    /**
     * Record a file edit.
     *
     * @param string $path The path of the edited file.
     */
    public function edit(string $path): void
    {
        $this->changes['edited'][] = $path;
    }

    /**
     * Record a file deletion.
     *
     * @param string $path The path of the deleted file.
     */
    public function delete(string $path): void
    {
        $this->changes['deleted'][] = $path;
    }

    /**
     * Revert a file change.
     *
     * @param string $path The path of the file to revert.
     */
    public function revert(string $path): void
    {
        foreach ($this->changes as $type => $files) {
            $this->changes[$type] = array_filter(
                $files,
                fn($f) => $f !== $path
            );
        }
    }

    /**
     * Get all recorded changes.
     *
     * @return array The array of changes.
     */
    public function changes(): array
    {
        return $this->changes;
    }
}
