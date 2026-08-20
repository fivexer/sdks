<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Aggregate reward statistics.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class LearningStats
{
    public function __construct(
        public readonly int $decisions,
        public readonly int $rewards,
        public readonly float $totalReward,
        public readonly float $averageReward,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            decisions: (int) ($data['decisions'] ?? 0),
            rewards: (int) ($data['rewards'] ?? 0),
            totalReward: (float) ($data['totalReward'] ?? 0),
            averageReward: (float) ($data['averageReward'] ?? 0),
        );
    }
}
