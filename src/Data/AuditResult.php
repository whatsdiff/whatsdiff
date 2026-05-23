<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Illuminate\Support\Collection;
use Whatsdiff\Enums\Severity;

final readonly class AuditResult
{
    /**
     * @param  Collection<int, PackageAudit>  $audits
     */
    public function __construct(
        public Collection $audits,
        public bool $isDiffMode = false,
        public ?string $fromCommit = null,
        public ?string $toCommit = null,
    ) {}

    public function hasVulnerabilities(): bool
    {
        return $this->audits->isNotEmpty();
    }

    public function maxSeverity(): Severity
    {
        $max = Severity::Unknown;

        foreach ($this->audits as $audit) {
            $auditMax = $audit->maxSeverity();
            if ($auditMax->rank() > $max->rank()) {
                $max = $auditMax;
            }
        }

        return $max;
    }

    /**
     * @return array<string, int>
     */
    public function countBySeverity(): array
    {
        $counts = [
            Severity::Critical->value => 0,
            Severity::High->value => 0,
            Severity::Medium->value => 0,
            Severity::Low->value => 0,
            Severity::Unknown->value => 0,
        ];

        foreach ($this->audits as $audit) {
            foreach ($audit->advisories as $advisory) {
                $counts[$advisory->severity->value]++;
            }
        }

        return $counts;
    }

    public function totalAdvisories(): int
    {
        return $this->audits->sum(fn (PackageAudit $audit) => count($audit->advisories));
    }

    /**
     * Whether any advisory's severity meets or exceeds the given threshold.
     *
     * When $countUnrated is true (the default), advisories with Severity::Unknown
     * are treated as meeting any threshold — fail-safe behavior for CI, because
     * an unrated CVE could be anything from low to critical. Pass false to
     * exclude unrated advisories from the check (--allow-unrated).
     */
    public function hasAnyAtOrAbove(Severity $threshold, bool $countUnrated = true): bool
    {
        foreach ($this->audits as $audit) {
            foreach ($audit->advisories as $advisory) {
                if ($countUnrated && $advisory->severity === Severity::Unknown) {
                    return true;
                }
                if ($advisory->severity->meetsThreshold($threshold)) {
                    return true;
                }
            }
        }

        return false;
    }
}
