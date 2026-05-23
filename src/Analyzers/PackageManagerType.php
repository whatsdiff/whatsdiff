<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

enum PackageManagerType: string
{
    case COMPOSER = 'composer';
    case NPM = 'npmjs';
    case PNPM = 'pnpm';

    public function getLabel(): string
    {
        return match ($this) {
            self::COMPOSER => 'Composer',
            self::NPM => 'npm',
            self::PNPM => 'pnpm',
        };
    }

    public function getLockFileName(): string
    {
        return match ($this) {
            self::COMPOSER => 'composer.lock',
            self::NPM => 'package-lock.json',
            self::PNPM => 'pnpm-lock.yaml',
        };
    }

    public function getRegistryUrl(string $package = ''): string
    {
        return match ($this) {
            self::COMPOSER => $package ? "https://repo.packagist.org/p2/{$package}.json" : 'https://repo.packagist.org',
            self::NPM, self::PNPM => $package ? 'https://registry.npmjs.org/'.urlencode($package) : 'https://registry.npmjs.org',
        };
    }

    public function getAllLockFileNames(): array
    {
        return array_map(fn (self $type) => $type->getLockFileName(), self::cases());
    }
}
