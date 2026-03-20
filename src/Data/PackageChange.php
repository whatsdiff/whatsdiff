<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Enums\ChangeStatus;
use Whatsdiff\Enums\Semver;

final readonly class PackageChange
{
    /**
     * @param array<SecurityAdvisory> $fixedAdvisories
     */
    public function __construct(
        public string $name,
        public PackageManagerType $type,
        public ?string $from,
        public ?string $to,
        public ChangeStatus $status,
        public ?int $releaseCount = null,
        public ?Semver $semver = null,
        public array $fixedAdvisories = [],
    ) {
    }

    public static function added(string $name, PackageManagerType $type, string $version): self
    {
        return new self(
            name: $name,
            type: $type,
            from: null,
            to: $version,
            status: ChangeStatus::Added,
        );
    }

    public static function removed(string $name, PackageManagerType $type, string $version): self
    {
        return new self(
            name: $name,
            type: $type,
            from: $version,
            to: null,
            status: ChangeStatus::Removed,
        );
    }

    /**
     * @param array<SecurityAdvisory> $fixedAdvisories
     */
    public static function updated(
        string $name,
        PackageManagerType $type,
        string $fromVersion,
        string $toVersion,
        ?int $releaseCount = null,
        ?Semver $semver = null,
        array $fixedAdvisories = [],
    ): self {
        return new self(
            name: $name,
            type: $type,
            from: $fromVersion,
            to: $toVersion,
            status: ChangeStatus::Updated,
            releaseCount: $releaseCount,
            semver: $semver,
            fixedAdvisories: $fixedAdvisories,
        );
    }

    /**
     * @param array<SecurityAdvisory> $fixedAdvisories
     */
    public static function downgraded(
        string $name,
        PackageManagerType $type,
        string $fromVersion,
        string $toVersion,
        ?int $releaseCount = null,
        ?Semver $semver = null,
        array $fixedAdvisories = [],
    ): self {
        return new self(
            name: $name,
            type: $type,
            from: $fromVersion,
            to: $toVersion,
            status: ChangeStatus::Downgraded,
            releaseCount: $releaseCount,
            semver: $semver,
            fixedAdvisories: $fixedAdvisories,
        );
    }
}
