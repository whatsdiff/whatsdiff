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
     * Get all deprecated items from all releases.
     *
     * @return array<int, string>
     */
    public function getDeprecated(): array
    {
        $deprecated = [];

        foreach ($this->releases as $release) {
            foreach ($release->getDeprecated() as $item) {
                $deprecated[] = $item;
            }
        }

        return $deprecated;
    }

    /**
     * Get all removed items from all releases.
     *
     * @return array<int, string>
     */
    public function getRemoved(): array
    {
        $removed = [];

        foreach ($this->releases as $release) {
            foreach ($release->getRemoved() as $item) {
                $removed[] = $item;
            }
        }

        return $removed;
    }

    /**
     * Get all security items from all releases.
     *
     * @return array<int, string>
     */
    public function getSecurity(): array
    {
        $security = [];

        foreach ($this->releases as $release) {
            foreach ($release->getSecurity() as $item) {
                $security[] = $item;
            }
        }

        return $security;
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

            if (!empty($release->title) && $release->title !== $release->tagName) {
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

        // Show version range and count
        $firstTag = $this->releases[0]->tagName;
        $lastTag = $this->releases[count($this->releases) - 1]->tagName;
        $count = count($this->releases);

        $markdown = "# Release Notes Summary\n\n";
        $markdown .= "**Releases:** {$firstTag} → {$lastTag} ({$count} versions)\n\n";

        // If any release is unstructured, show all bullet points in a flat list
        if ($this->hasUnstructuredReleases()) {
            $allBulletPoints = $this->getAllBulletPoints();
            if (!empty($allBulletPoints)) {
                $markdown .= "## All Changes\n\n";
                foreach ($allBulletPoints as $bulletPoint) {
                    $markdown .= "- {$this->formatGithubUrls($bulletPoint)}\n";
                }
                $markdown .= "\n";
            }
            return trim($markdown);
        }

        // All releases are structured - show categorized sections
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

        // Deprecated section
        $deprecated = $this->getDeprecated();
        if (!empty($deprecated)) {
            $markdown .= "## Deprecated\n\n";
            foreach ($deprecated as $item) {
                $markdown .= "- {$this->formatGithubUrls($item)}\n";
            }
            $markdown .= "\n";
        }

        // Removed section
        $removed = $this->getRemoved();
        if (!empty($removed)) {
            $markdown .= "## Removed\n\n";
            foreach ($removed as $item) {
                $markdown .= "- {$this->formatGithubUrls($item)}\n";
            }
            $markdown .= "\n";
        }

        // Security section
        $security = $this->getSecurity();
        if (!empty($security)) {
            $markdown .= "## Security\n\n";
            foreach ($security as $item) {
                $markdown .= "- {$this->formatGithubUrls($item)}\n";
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
     * Check if any release in the collection is unstructured.
     *
     * Returns true if at least one release doesn't follow a recognizable
     * changelog format (Keep a Changelog, standard sections, etc.).
     */
    public function hasUnstructuredReleases(): bool
    {
        foreach ($this->releases as $release) {
            if (!$release->isStructured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all bullet points from all releases, regardless of sections.
     *
     * This is useful for summary views when dealing with mixed structured
     * and unstructured changelogs - providing a flat list of all changes.
     *
     * @return array<int, string>
     */
    public function getAllBulletPoints(): array
    {
        $bulletPoints = [];

        foreach ($this->releases as $release) {
            foreach ($release->getAllBulletPoints() as $bulletPoint) {
                $bulletPoints[] = $bulletPoint;
            }
        }

        return $bulletPoints;
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
