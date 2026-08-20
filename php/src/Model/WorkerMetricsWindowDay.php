<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One day's completed count in a rolling metrics window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetricsWindowDay
{
    public function __construct(
        public readonly string $day,
        public readonly int $completed,
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
            completed: (int) ($data['completed'] ?? 0),
        );
    }
}
