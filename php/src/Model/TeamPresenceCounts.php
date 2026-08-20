<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Presence totals by state.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamPresenceCounts
{
    public function __construct(
        public readonly int $working,
        public readonly int $onBreak,
        public readonly int $paused,
        public readonly int $total,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            working: (int) ($data['working'] ?? 0),
            onBreak: (int) ($data['onBreak'] ?? 0),
            paused: (int) ($data['paused'] ?? 0),
            total: (int) ($data['total'] ?? 0),
        );
    }
}
