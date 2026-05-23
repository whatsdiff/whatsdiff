<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Whatsdiff\Enums\Severity;

final readonly class SecurityAdvisory
{
    public Severity $severity;

    public function __construct(
        public string $advisoryId,
        public ?string $cve,
        public string $title,
        public string $link,
        public string $affectedVersions,
        ?Severity $severity = null,
    ) {
        $this->severity = $severity ?? Severity::Unknown;
    }

    public function withSeverity(Severity $severity): self
    {
        return new self(
            advisoryId: $this->advisoryId,
            cve: $this->cve,
            title: $this->title,
            link: $this->link,
            affectedVersions: $this->affectedVersions,
            severity: $severity,
        );
    }
}
