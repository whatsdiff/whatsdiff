<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use DateTimeImmutable;

final readonly class ReleaseNote
{
    private bool $isStructured;

    public function __construct(
        public string $tagName,
        public string $title,
        public string $body,
        public DateTimeImmutable $date,
        public ?string $url = null,
    ) {
        $this->isStructured = $this->detectStructure();
    }

    /**
     * Check if the changelog follows a recognizable structure.
     *
     * Returns true if the body contains:
     * - Keep a Changelog format (## [version] - date)
     * - Common section headings (Changes, Fixes, Breaking, etc.)
     * - Structured markdown sections
     */
    public function isStructured(): bool
    {
        return $this->isStructured;
    }

    /**
     * Detect if the changelog body follows a recognizable structure.
     *
     * Called once during construction to determine if the changelog
     * follows standard patterns like Keep a Changelog or common section headings.
     */
    private function detectStructure(): bool
    {
        $lines = explode("\n", $this->body);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check for Keep a Changelog format: ## [1.0.0] - 2024-01-01
            if (preg_match('/^#{2,3}\s+\[\d+\.\d+/', $trimmedLine)) {
                return true;
            }

            // Check for common section headings (markdown ## or ###)
            if (preg_match('/^#{2,3}\s+(Changed?|Added?|What\'?s Changed|New Features|Features|Enhancements|Improvements|Fixes?|Fixed|Bug ?Fixes?|Bugfixes|Breaking( Changes)?|BREAKING CHANGES|Removed?|Deprecated|Security)/i', $trimmedLine)) {
                return true;
            }

            // Check for bold section headings
            if (preg_match('/\*\*\s*(Changed?|Added?|What\'?s Changed|New Features|Features|Enhancements|Improvements|Fixes?|Fixed|Bug ?Fixes?|Bugfixes|Breaking( Changes)?|BREAKING CHANGES|Removed?|Deprecated|Security)\s*\*\*/i', $trimmedLine)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract "Changes", "Changed", or "Added" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getChanges(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Changed?|Added?|What\'?s Changed|New Features|Features|Enhancements|Improvements)/i');
    }

    /**
     * Extract "Fixes" or "Fixed" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getFixes(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Fixes?|Fixed|Bug ?Fixes?|Bugfixes)/i');
    }

    /**
     * Extract "Breaking Changes" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getBreakingChanges(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Breaking( Changes)?|BREAKING CHANGES)/i');
    }

    /**
     * Extract "Deprecated" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getDeprecated(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Deprecated)/i');
    }

    /**
     * Extract "Removed" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getRemoved(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Removed?)/i');
    }

    /**
     * Extract "Security" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getSecurity(): array
    {
        if (!$this->isStructured()) {
            return [];
        }

        return $this->extractSectionByPattern('/^#{2,3}\s+(Security)/i');
    }

    /**
     * Get the full markdown body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get the release title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Extract all bullet points from the body regardless of sections.
     *
     * This is useful for summary views when dealing with unstructured changelogs,
     * or when you want all changes in a flat list without categorization.
     *
     * @return array<int, string>
     */
    public function getAllBulletPoints(): array
    {
        $lines = explode("\n", $this->body);
        $items = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Collect all bullet points (- or *)
            if (str_starts_with($trimmedLine, '- ') || str_starts_with($trimmedLine, '* ')) {
                $items[] = substr($trimmedLine, 2); // Remove bullet point
            }
        }

        return $items;
    }

    /**
     * Extract description/introduction text that appears before structured sections
     * or text that's not part of recognized sections.
     *
     * Returns empty string if the changelog is not structured.
     */
    public function getDescription(): string
    {
        if (!$this->isStructured()) {
            return '';
        }

        $lines = explode("\n", $this->body);
        $description = [];
        $inRecognizedSection = false;
        $hasSeenHeading = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if this is a heading
            $isMarkdownHeading = str_starts_with($trimmedLine, '## ') || str_starts_with($trimmedLine, '### ');
            $isBoldHeading = preg_match('/\*\*[^*]+\*\*/', $trimmedLine);

            if ($isMarkdownHeading || $isBoldHeading) {
                $hasSeenHeading = true;

                // Check if it's a recognized section using regex
                $isRecognized = preg_match('/^#{2,3}\s+(Changed?|Added?|What\'?s Changed|New Features|Features|Enhancements|Improvements|Fixes?|Fixed|Bug ?Fixes?|Bugfixes|Breaking( Changes)?|BREAKING CHANGES|Removed?|Deprecated|Security)/i', $trimmedLine)
                    || preg_match('/\*\*\s*(Changed?|Added?|What\'?s Changed|New Features|Features|Enhancements|Improvements|Fixes?|Fixed|Bug ?Fixes?|Bugfixes|Breaking( Changes)?|BREAKING CHANGES|Removed?|Deprecated|Security)\s*\*\*/i', $trimmedLine);

                $inRecognizedSection = (bool) $isRecognized;
                continue;
            }

            // If we haven't seen any heading yet, or we're in an unrecognized section,
            // and this isn't a bullet point, collect it as description
            if ((!$hasSeenHeading || !$inRecognizedSection) &&
                !str_starts_with($trimmedLine, '- ') &&
                !str_starts_with($trimmedLine, '* ')) {
                $description[] = $line; // Keep original line with indentation
            }
        }

        // Join and trim the description, removing excessive blank lines
        $descriptionText = implode("\n", $description);
        $descriptionText = trim($descriptionText);

        // Normalize multiple consecutive newlines to at most 2 (one blank line)
        $descriptionText = preg_replace("/\n{3,}/", "\n\n", $descriptionText);

        return $descriptionText;
    }

    /**
     * Extract bullet points from sections matching a regex pattern.
     *
     * This method also supports bold headings like **Changes** in addition to markdown headings.
     *
     * @param string $headingPattern Regex pattern to match section headings
     * @return array<int, string>
     */
    private function extractSectionByPattern(string $headingPattern): array
    {
        $lines = explode("\n", $this->body);
        $items = [];
        $inSection = false;

        // Create a bold heading pattern from the markdown heading pattern
        // Example: '/^#{2,3}\s+(Changes|Added)/i' becomes '/\*\*\s*(Changes|Added)\s*\*\*/i'
        $boldPattern = str_replace(
            ['/^#{2,3}\s+', '/i'],
            ['/\*\*\s*', '\s*\*\*/i'],
            $headingPattern
        );

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if we're starting a section we care about
            if (preg_match($headingPattern, $trimmedLine) || preg_match($boldPattern, $trimmedLine)) {
                $inSection = true;
                continue; // Skip the heading line itself
            }

            // Check if we're starting a different section (stop collecting)
            $isNewSection = str_starts_with($trimmedLine, '## ')
                         || str_starts_with($trimmedLine, '### ')
                         || preg_match('/\*\*[^*]+\*\*/', $trimmedLine);

            if ($inSection && $isNewSection) {
                // Check if this new section matches our pattern
                if (!preg_match($headingPattern, $trimmedLine) && !preg_match($boldPattern, $trimmedLine)) {
                    $inSection = false;
                }
                continue;
            }

            // Collect bullet points
            if ($inSection && (str_starts_with($trimmedLine, '- ') || str_starts_with($trimmedLine, '* '))) {
                $items[] = substr($trimmedLine, 2); // Remove bullet point
            }
        }

        return $items;
    }
}
