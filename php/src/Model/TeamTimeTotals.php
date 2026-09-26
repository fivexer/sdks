<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The team working-time record summed across every worker in the window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamTimeTotals
{
    public function __construct(
        public readonly int $workerCount,
        public readonly int $shiftCount,
        public readonly int $onShiftMs,
        public readonly int $breakCount,
        public readonly int $breakMs,
        public readonly int $workingMs,
        public readonly int $completed,
        public readonly int $offered,
        public readonly int $accepted,
        public readonly int $rejected,
        public readonly int $expired,
        public readonly int $failed,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerCount: (int) ($data['workerCount'] ?? 0),
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
            completed: (int) ($data['completed'] ?? 0),
            offered: (int) ($data['offered'] ?? 0),
            accepted: (int) ($data['accepted'] ?? 0),
            rejected: (int) ($data['rejected'] ?? 0),
            expired: (int) ($data['expired'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
        );
    }
}
