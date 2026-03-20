<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

final readonly class SecurityAdvisory
{
    public function __construct(
        public string $advisoryId,
        public ?string $cve,
        public string $title,
        public string $link,
        public string $affectedVersions,
    ) {
    }
}
