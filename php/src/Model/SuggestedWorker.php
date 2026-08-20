<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One candidate from a dry-run scoring pass.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SuggestedWorker
{
    public function __construct(
        public readonly string $workerId,
        public readonly bool $eligible,
        public readonly float $score,
        public readonly float $effectivePriority,
        /** @var list<array<string, mixed>> */
        public readonly array $reasons,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            eligible: (bool) ($data['eligible'] ?? false),
            score: (float) ($data['score'] ?? 0),
            effectivePriority: (float) ($data['effectivePriority'] ?? 0),
            reasons: $data['reasons'] ?? [],
        );
    }
}
