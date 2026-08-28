<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker's window report: what they finished, how they responded, and how long they worked.
 *
 * Three sources merged server-side — the task archive (throughput and handle times), the synced
 * day counters (offers, accepts, rejections) and the shift log (`onShiftMs` less overlapping
 * breaks is `workingMs`). Measured facts, never a score.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerProductivity
{
    public function __construct(
        public readonly string $workerId,
        public readonly int $completed,
        public readonly int $cancelled,
        public readonly ?float $avgWaitMs,
        public readonly ?float $avgHandleMs,
        public readonly ?float $p50HandleMs = null,
        public readonly ?float $p95HandleMs = null,
        public readonly int $offered = 0,
        public readonly int $accepted = 0,
        public readonly int $rejected = 0,
        public readonly int $failed = 0,
        public readonly int $expired = 0,
        public readonly int $released = 0,
        /** accepted / offered over the window; null before any offer, never zero. */
        public readonly ?float $acceptanceRate = null,
        /** Time on shift inside the window, breaks included. */
        public readonly int $onShiftMs = 0,
        public readonly int $breakMs = 0,
        /** onShiftMs − breakMs, floored at zero — worked time. */
        public readonly int $workingMs = 0,
        public readonly int $shiftCount = 0,
        /** Handle time ÷ worked time. May exceed 1 for overlapping tasks; null when unmeasured. */
        public readonly ?float $utilization = null,
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
            completed: (int) ($data['completed'] ?? 0),
            cancelled: (int) ($data['cancelled'] ?? 0),
            avgWaitMs: isset($data['avgWaitMs']) ? (float) $data['avgWaitMs'] : null,
            avgHandleMs: isset($data['avgHandleMs']) ? (float) $data['avgHandleMs'] : null,
            p50HandleMs: isset($data['p50HandleMs']) ? (float) $data['p50HandleMs'] : null,
            p95HandleMs: isset($data['p95HandleMs']) ? (float) $data['p95HandleMs'] : null,
            offered: (int) ($data['offered'] ?? 0),
            accepted: (int) ($data['accepted'] ?? 0),
            rejected: (int) ($data['rejected'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            expired: (int) ($data['expired'] ?? 0),
            released: (int) ($data['released'] ?? 0),
            acceptanceRate: isset($data['acceptanceRate']) ? (float) $data['acceptanceRate'] : null,
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            utilization: isset($data['utilization']) ? (float) $data['utilization'] : null,
        );
    }
}
