<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Whatsdiff\Analyzers\PackageManagerType;

final readonly class PackageReleaseNotes
{
    public function __construct(
        public string $package,
        public PackageManagerType $type,
        public string $fromVersion,
        public string $toVersion,
        public ?ReleaseNotesCollection $releaseNotes,
    ) {
    }

    public function hasReleaseNotes(): bool
    {
        return $this->releaseNotes !== null && ! $this->releaseNotes->isEmpty();
    }
}
