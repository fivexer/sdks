<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Learning configuration plus its current statistics.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class LearningStatus
{
    public function __construct(
        public readonly bool $enabled,
        /** true = the model scores but never influences routing */
        public readonly bool $shadowMode,
        public readonly bool $autoWeights,
        public readonly int $modelSize,
        /** @var array<string, float>|null */
        public readonly ?array $signalWeights,
        /** @var array<string, float>|null */
        public readonly ?array $rewards,
        /** null until the first reward lands */
        public readonly ?LearningStats $stats,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            shadowMode: (bool) ($data['shadowMode'] ?? false),
            autoWeights: (bool) ($data['autoWeights'] ?? false),
            modelSize: (int) ($data['modelSize'] ?? 0),
            signalWeights: $data['signalWeights'] ?? null,
            rewards: $data['rewards'] ?? null,
            stats: isset($data['stats']) ? LearningStats::fromArray($data['stats']) : null,
        );
    }
}
