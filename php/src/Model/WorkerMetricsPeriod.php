<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One worker's rolling window: recorded time, throughput and response counters.
 *
 * Medians are null for a worker with no history, and `acceptanceRate` is null before any offer —
 * a rate over nothing is not zero.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetricsPeriod
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        /** @var list<WorkerMetricsWindowDay>|null the per-day counts, or null when not broken down */
        public readonly ?array $days,
        public readonly ?int $medianWaitMs,
        public readonly ?int $medianCycleMs,
        public readonly int $shiftCount,
        /** Time on shift in the window, breaks included. */
        public readonly int $onShiftMs,
        public readonly int $breakMs,
        /** onShiftMs − breakMs, worked time. */
        public readonly int $workingMs,
        public readonly int $offered,
        public readonly int $accepted,
        public readonly int $rejected,
        public readonly int $completed,
        public readonly int $failed,
        public readonly int $expired,
        public readonly int $released,
        /** accepted / offered over the window; null before any offer, never zero. */
        public readonly ?float $acceptanceRate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            days: Json::parseEachOrNull($data, 'days', [WorkerMetricsWindowDay::class, 'fromArray']),
            medianWaitMs: isset($data['medianWaitMs']) ? (int) $data['medianWaitMs'] : null,
            medianCycleMs: isset($data['medianCycleMs']) ? (int) $data['medianCycleMs'] : null,
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
            offered: (int) ($data['offered'] ?? 0),
            accepted: (int) ($data['accepted'] ?? 0),
            rejected: (int) ($data['rejected'] ?? 0),
            completed: (int) ($data['completed'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            expired: (int) ($data['expired'] ?? 0),
            released: (int) ($data['released'] ?? 0),
            acceptanceRate: isset($data['acceptanceRate']) ? (float) $data['acceptanceRate'] : null,
        );
    }
}
