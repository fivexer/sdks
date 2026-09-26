<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One UTC day of the team's time, split from the same spans as the totals.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamTimeDay
{
    public function __construct(
        /** ISO date (UTC) */
        public readonly string $day,
        public readonly int $onShiftMs,
        public readonly int $breakMs,
        public readonly int $workingMs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            day: (string) ($data['day'] ?? ''),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
        );
    }
}
