<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The board's headline numbers.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorCounts
{
    public function __construct(
        public readonly int $queued,
        public readonly int $pending,
        public readonly int $parked,
        public readonly int $oldestWaitMs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            queued: (int) ($data['queued'] ?? 0),
            pending: (int) ($data['pending'] ?? 0),
            parked: (int) ($data['parked'] ?? 0),
            oldestWaitMs: (int) ($data['oldestWaitMs'] ?? 0),
        );
    }
}
