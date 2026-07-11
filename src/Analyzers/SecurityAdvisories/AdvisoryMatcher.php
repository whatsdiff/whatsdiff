<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\SecurityAdvisories;

use Composer\Semver\Semver;
use Whatsdiff\Data\SecurityAdvisory;

/**
 * Pure advisory-to-version matching, shared by the audit and diff calculators
 * (and usable standalone by consumers that fetch advisories themselves).
 *
 * Semantics are intentionally forgiving: an advisory whose constraint cannot
 * be parsed (or is empty) is skipped rather than treated as matching, so a
 * single malformed advisory never fails a whole audit.
 */
final class AdvisoryMatcher
{
    /**
     * Advisories whose affected range matches the installed version.
     *
     * @param  array<SecurityAdvisory>  $advisories
     * @return array<SecurityAdvisory>
     */
    public static function affecting(array $advisories, string $version): array
    {
        $affecting = [];

        foreach ($advisories as $advisory) {
            if ($advisory->affectedVersions === '') {
                continue;
            }

            try {
                if (Semver::satisfies($version, $advisory->affectedVersions)) {
                    $affecting[] = $advisory;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $affecting;
    }

    /**
     * Advisories fixed by upgrading from $from to $to: the range matches the
     * old version but no longer matches the new one.
     *
     * @param  array<SecurityAdvisory>  $advisories
     * @return array<SecurityAdvisory>
     */
    public static function fixedBetween(array $advisories, string $from, string $to): array
    {
        $fixed = [];

        foreach ($advisories as $advisory) {
            if ($advisory->affectedVersions === '') {
                continue;
            }

            try {
                if (Semver::satisfies($from, $advisory->affectedVersions)
                    && ! Semver::satisfies($to, $advisory->affectedVersions)) {
                    $fixed[] = $advisory;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $fixed;
    }

    /**
     * Advisories newly affecting $to that did not affect $from (diff-mode
     * audit semantics). A null $from (package added) means every advisory
     * affecting $to counts as introduced; a $from that fails to parse against
     * the range is treated as previously unaffected.
     *
     * @param  array<SecurityAdvisory>  $advisories
     * @return array<SecurityAdvisory>
     */
    public static function introducedBetween(array $advisories, ?string $from, string $to): array
    {
        $introduced = [];

        foreach (self::affecting($advisories, $to) as $advisory) {
            if ($from === null) {
                $introduced[] = $advisory;

                continue;
            }

            try {
                $fromAffected = Semver::satisfies($from, $advisory->affectedVersions);
            } catch (\Exception $e) {
                $fromAffected = false;
            }

            if (! $fromAffected) {
                $introduced[] = $advisory;
            }
        }

        return $introduced;
    }
}
