<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

class GitRepository
{
    private ?string $gitRoot = null;
    private ?string $currentDir = null;
    private ?string $relativeCurrentDir = null;
    private ProcessService $processService;
    private bool $initialized = false;

    public function __construct(?ProcessService $processService = null)
    {
        $this->processService = $processService ?? new ProcessService();
    }

    /**
     * Initialize git repository properties on first use.
     * This allows the service to be instantiated by DI containers without throwing exceptions.
     *
     * @throws \RuntimeException if not in a git repository
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $process = $this->processService->git(['rev-parse', '--show-toplevel']);

        if (!$process->isSuccessful() || empty(trim($process->getOutput()))) {
            throw new \RuntimeException('Not in a git repository or git command failed');
        }

        $this->gitRoot = rtrim(trim($process->getOutput()), DIRECTORY_SEPARATOR);
        $this->currentDir = rtrim(getcwd() ?: '', DIRECTORY_SEPARATOR);

        // Normalize paths on Windows to handle path separator differences
        $normalizedGitRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->gitRoot);
        $normalizedCurrentDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->currentDir);

        // Also handle Windows short vs long path names
        if (PHP_OS_FAMILY === 'Windows') {
            $normalizedGitRoot = strtolower(realpath($normalizedGitRoot) ?: $normalizedGitRoot);
            $normalizedCurrentDir = strtolower(realpath($normalizedCurrentDir) ?: $normalizedCurrentDir);
        }

        $this->relativeCurrentDir = ltrim(str_replace($normalizedGitRoot, '', $normalizedCurrentDir), DIRECTORY_SEPARATOR);
        $this->initialized = true;
    }

    public function getGitRoot(): string
    {
        $this->ensureInitialized();

        return $this->gitRoot;
    }

    public function getCurrentDir(): string
    {
        $this->ensureInitialized();

        return $this->currentDir;
    }

    public function getRelativeCurrentDir(): string
    {
        $this->ensureInitialized();

        return $this->relativeCurrentDir;
    }

    public function getFileCommitLogs(string $filename, string $beforeHash = ''): array
    {
        $this->ensureInitialized();

        $args = ['log', '--pretty=format:%h', '--', $filename];

        if ($beforeHash) {
            array_splice($args, 1, 0, $beforeHash);
        }

        $process = $this->processService->git($args, $this->gitRoot);


        if (!$process->isSuccessful() || empty(trim($process->getOutput()))) {
            return [];
        }

        return explode("\n", trim($process->getOutput()));
    }

    public function getMultipleFilesCommitLogs(array $filenames): array
    {
        $this->ensureInitialized();

        $args = array_merge(['log', '--pretty=format:%h', '--'], $filenames);

        $process = $this->processService->git($args, $this->gitRoot);

        if (!$process->isSuccessful() || empty(trim($process->getOutput()))) {
            return [];
        }

        return explode("\n", trim($process->getOutput()));
    }

    public function isFileRecentlyUpdated(string $filename): bool
    {
        $this->ensureInitialized();

        $process = $this->processService->git(['status', '--porcelain'], $this->gitRoot);

        if (!$process->isSuccessful() || empty(trim($process->getOutput()))) {
            return false;
        }

        $lines = explode("\n", trim($process->getOutput()));
        $status = collect($lines)
            ->filter()
            ->mapWithKeys(function ($line) {
                $parts = array_values(array_filter(explode(' ', $line)));
                return isset($parts[1]) ? [$parts[1] => $parts[0]] : [];
            });

        // If the file exists and is not in the list of untracked files
        if (!empty($this->relativeCurrentDir) && file_exists($filename) && !$status->has($filename)) {
            return true; // Created
        }

        return in_array($status->get($filename), [
            'AM', // Added and modified
            'M',  // Modified
            'A',  // Added
            '??', // Untracked
        ]);
    }

    public function getFileContentAtCommit(string $filename, string $commitHash): string
    {
        $this->ensureInitialized();

        $process = $this->processService->git(
            ['show', $commitHash . ':' . $filename],
            $this->gitRoot
        );

        return $process->isSuccessful() ? $process->getOutput() : '';
    }

    public function validateCommit(string $commit): bool
    {
        $this->ensureInitialized();

        $process = $this->processService->git(
            ['rev-parse', '--verify', $commit],
            $this->gitRoot
        );

        return $process->isSuccessful();
    }

    public function resolveCommitHash(string $commit): string
    {
        $this->ensureInitialized();

        $process = $this->processService->git(
            ['rev-parse', $commit],
            $this->gitRoot
        );

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Invalid commit reference: {$commit}");
        }

        return trim($process->getOutput());
    }

    public function getShortCommitHash(string $commit): string
    {
        $this->ensureInitialized();

        $process = $this->processService->git(
            ['rev-parse', '--short', $commit],
            $this->gitRoot
        );

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Invalid commit reference: {$commit}");
        }

        return trim($process->getOutput());
    }
}
