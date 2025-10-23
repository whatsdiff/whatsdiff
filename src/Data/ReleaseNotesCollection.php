<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Countable;
use IteratorAggregate;
use Traversable;
use Whatsdiff\Helpers\GithubUrlFormatter;

/**
 * Collection of release notes with merge capabilities.
 *
 * @implements IteratorAggregate<int, ReleaseNote>
 */
final readonly class ReleaseNotesCollection implements Countable, IteratorAggregate
{
    /**
     * @param array<int, ReleaseNote> $releases
     */
    public function __construct(
        private array $releases = []
    ) {
    }

    /**
     * Get all release notes.
     *
     * @return array<int, ReleaseNote>
     */
    public function getReleases(): array
    {
        return $this->releases;
    }

    /**
     * Get all changes from all releases.
     *
     * @return array<int, string>
     */
    public function getChanges(): array
    {
        $changes = [];

        foreach ($this->releases as $release) {
            foreach ($release->getChanges() as $change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * Get all fixes from all releases.
     *
     * @return array<int, string>
     */
    public function getFixes(): array
    {
        $fixes = [];

        foreach ($this->releases as $release) {
            foreach ($release->getFixes() as $fix) {
                $fixes[] = $fix;
            }
        }

        return $fixes;
    }

    /**
     * Get all breaking changes from all releases.
     *
     * @return array<int, string>
     */
    public function getBreakingChanges(): array
    {
        $breakingChanges = [];

        foreach ($this->releases as $release) {
            foreach ($release->getBreakingChanges() as $breakingChange) {
                $breakingChanges[] = $breakingChange;
            }
        }

        return $breakingChanges;
    }

    /**
     * Merge all release notes into a single markdown document.
     */
    public function toMarkdown(): string
    {
        if (empty($this->releases)) {
            return '';
        }

        $markdown = '';

        // Add each release as a section
        foreach ($this->releases as $release) {
            $markdown .= "## {$release->tagName}";

            if ($release->title !== $release->tagName) {
                $markdown .= " - {$release->title}";
            }

            $markdown .= "\n\n";

            if ($release->url) {
                $markdown .= "**Release URL:** {$release->url}\n\n";
            }

            $markdown .= "**Date:** {$release->date->format('Y-m-d')}\n\n";

            // Add the body with formatted GitHub URLs
            $markdown .= $this->formatGithubUrls($release->body);
            $markdown .= "\n\n---\n\n";
        }

        return rtrim($markdown, "\n-");
    }

    /**
     * Merge all releases into a summarized markdown with combined sections.
     */
    public function toSummarizedMarkdown(): string
    {
        if (empty($this->releases)) {
            return '';
        }

        $markdown = "# Release Notes Summary\n\n";
        $markdown .= "**Releases:** " . count($this->releases) . "\n\n";

        // Breaking Changes section
        $breakingChanges = $this->getBreakingChanges();
        if (!empty($breakingChanges)) {
            $markdown .= "## Breaking Changes\n\n";
            foreach ($breakingChanges as $change) {
                $markdown .= "- {$this->formatGithubUrls($change)}\n";
            }
            $markdown .= "\n";
        }

        // Changes section
        $changes = $this->getChanges();
        if (!empty($changes)) {
            $markdown .= "## Changes\n\n";
            foreach ($changes as $change) {
                $markdown .= "- {$this->formatGithubUrls($change)}\n";
            }
            $markdown .= "\n";
        }

        // Fixes section
        $fixes = $this->getFixes();
        if (!empty($fixes)) {
            $markdown .= "## Fixes\n\n";
            foreach ($fixes as $fix) {
                $markdown .= "- {$this->formatGithubUrls($fix)}\n";
            }
            $markdown .= "\n";
        }

        return trim($markdown);
    }

    /**
     * Count the number of releases.
     */
    public function count(): int
    {
        return count($this->releases);
    }

    /**
     * Get iterator for traversing releases.
     *
     * @return Traversable<int, ReleaseNote>
     */
    public function getIterator(): Traversable
    {
        yield from $this->releases;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->releases);
    }

    /**
     * Format GitHub PR/issue URLs in text to compact markdown links.
     * Converts: https://github.com/owner/repo/pull/123 -> [#123](url)
     * Converts: https://github.com/owner/repo/issues/456 -> [#456](url)
     */
    private function formatGithubUrls(string $text): string
    {
        return GithubUrlFormatter::toMarkdownLink($text);
    }
}
