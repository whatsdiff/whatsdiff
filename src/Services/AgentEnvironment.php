<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Laravel\AgentDetector\AgentDetector;

final class AgentEnvironment
{
    public function __construct(
        public readonly bool $isAgent,
        public readonly ?string $agentName,
    ) {}

    public static function detect(): self
    {
        $result = AgentDetector::detect();

        return new self(
            isAgent: $result->isAgent,
            agentName: $result->isAgent ? ($result->name ?? 'unknown') : null,
        );
    }

    public static function noAgent(): self
    {
        return new self(isAgent: false, agentName: null);
    }

    public function defaultFormat(): string
    {
        return $this->isAgent ? 'json' : 'text';
    }
}
