<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Today's recorded time and throughput for one worker, as an operator reads it.
 *
 * A shift is a recorded stretch of availability and breaks are time inside one, so `workingMs`
 * is `onShiftMs` minus break time. Measured facts, never a score.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetricsDay
{
    public function __construct(
        public readonly string $since,
        public readonly int $completedTasks,
        public readonly int $shiftCount,
        /** Time on shift today, breaks included. */
        public readonly int $onShiftMs,
        public readonly int $breakCount,
        public readonly int $totalBreakMs,
        public readonly int $longestBreakMs,
        /** onShiftMs − totalBreakMs, worked time. */
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
            since: (string) ($data['since'] ?? ''),
            completedTasks: (int) ($data['completedTasks'] ?? 0),
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            totalBreakMs: (int) ($data['totalBreakMs'] ?? 0),
            longestBreakMs: (int) ($data['longestBreakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
        );
    }
}
