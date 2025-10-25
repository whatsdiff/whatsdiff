<?php

declare(strict_types=1);

namespace Whatsdiff\Helpers;

/**
 * Utility class for formatting GitHub PR/issue URLs to compact representations.
 */
class GithubUrlFormatter
{
    /**
     * Regex pattern for matching GitHub PR and issue URLs.
     * Matches: https://github.com/owner/repo/pull/123 or https://github.com/owner/repo/issues/456
     */
    private const PATTERN = 'https?:\/\/github\.com\/[^\/\s]+\/[^\/\s]+\/(?:pull|issues)\/(\d+)';

    /**
     * Convert GitHub URLs to terminal hyperlinks with compact format.
     * Example: https://github.com/owner/repo/pull/123 -> #123 (clickable)
     */
    public static function toTerminalLink(string $text): string
    {
        return preg_replace(
            '/(?<!href=)(' . self::PATTERN . ')/',
            '<href=$1>#$2</>',
            $text
        );
    }

    /**
     * Convert GitHub URLs to markdown links with compact format.
     * Example: https://github.com/owner/repo/pull/123 -> [#123](url)
     */
    public static function toMarkdownLink(string $text): string
    {
        return preg_replace(
            '/(?<!\()' . self::PATTERN . '/',
            '[#$1]($0)',
            $text
        );
    }

    /**
     * Convert GitHub URLs to plain short text format.
     * Example: https://github.com/owner/repo/pull/123 -> #123
     */
    public static function toShortText(string $text): string
    {
        return preg_replace(
            '/' . self::PATTERN . '/',
            '#$1',
            $text
        );
    }
}
