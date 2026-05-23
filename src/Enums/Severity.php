<?php

declare(strict_types=1);

namespace Whatsdiff\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::Unknown;
        }

        return match (strtolower(trim($value))) {
            'low' => self::Low,
            'medium', 'moderate' => self::Medium,
            'high' => self::High,
            'critical' => self::Critical,
            default => self::Unknown,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Unknown => 0,
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function meetsThreshold(self $threshold): bool
    {
        return $this->rank() >= $threshold->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
