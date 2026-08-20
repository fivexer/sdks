<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Per-tag learning statistics for one worker.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerLearningSkillStat
{
    public function __construct(
        public readonly string $tag,
        public readonly int $count,
        public readonly float $meanReward,
        public readonly ?float $currentWeight,
        public readonly ?float $learnedWeight,
        /** @var array<string, mixed>|null */
        public readonly ?array $skill,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tag: (string) ($data['tag'] ?? ''),
            count: (int) ($data['count'] ?? 0),
            meanReward: (float) ($data['meanReward'] ?? 0),
            currentWeight: isset($data['currentWeight']) ? (float) $data['currentWeight'] : null,
            learnedWeight: isset($data['learnedWeight']) ? (float) $data['learnedWeight'] : null,
            skill: $data['skill'] ?? null,
        );
    }
}
