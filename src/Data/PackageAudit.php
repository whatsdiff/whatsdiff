<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Enums\Severity;

final readonly class PackageAudit
{
    /**
     * @param  array<SecurityAdvisory>  $advisories
     */
    public function __construct(
        public string $name,
        public PackageManagerType $type,
        public string $installedVersion,
        public array $advisories,
        public ?string $suggestedFixVersion = null,
    ) {
    }

    public function maxSeverity(): Severity
    {
        $max = Severity::Unknown;

        foreach ($this->advisories as $advisory) {
            if ($advisory->severity->rank() > $max->rank()) {
                $max = $advisory->severity;
            }
        }

        return $max;
    }
}
