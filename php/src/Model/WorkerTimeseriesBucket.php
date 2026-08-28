<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One bucket of a single worker's series.
 *
 * Worked time and the lifecycle counters are day-grained, so they are present only on
 * `bucket=day` responses. They are null on hour buckets rather than zero: "not measured at this
 * resolution" is not the same answer as "measured as nothing".
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTimeseriesBucket
{
    public function __construct(
        public readonly string $bucketStart,
        public readonly int $completed,
        public readonly int $cancelled,
        public readonly ?float $avgWaitMs,
        public readonly ?float $p50WaitMs,
        public readonly ?float $p95WaitMs,
        public readonly ?float $avgHandleMs,
        /** Time on shift in this bucket, breaks included. Day buckets only. */
        public readonly ?int $onShiftMs = null,
        public readonly ?int $breakMs = null,
        /** onShiftMs − breakMs, worked time. Day buckets only. */
        public readonly ?int $workingMs = null,
        public readonly ?int $offered = null,
        public readonly ?int $accepted = null,
        public readonly ?int $rejected = null,
        public readonly ?int $failed = null,
        public readonly ?int $expired = null,
        public readonly ?int $released = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bucketStart: (string) ($data['bucketStart'] ?? ''),
            completed: (int) ($data['completed'] ?? 0),
            cancelled: (int) ($data['cancelled'] ?? 0),
            avgWaitMs: isset($data['avgWaitMs']) ? (float) $data['avgWaitMs'] : null,
            p50WaitMs: isset($data['p50WaitMs']) ? (float) $data['p50WaitMs'] : null,
            p95WaitMs: isset($data['p95WaitMs']) ? (float) $data['p95WaitMs'] : null,
            avgHandleMs: isset($data['avgHandleMs']) ? (float) $data['avgHandleMs'] : null,
            onShiftMs: isset($data['onShiftMs']) ? (int) $data['onShiftMs'] : null,
            breakMs: isset($data['breakMs']) ? (int) $data['breakMs'] : null,
            workingMs: isset($data['workingMs']) ? (int) $data['workingMs'] : null,
            offered: isset($data['offered']) ? (int) $data['offered'] : null,
            accepted: isset($data['accepted']) ? (int) $data['accepted'] : null,
            rejected: isset($data['rejected']) ? (int) $data['rejected'] : null,
            failed: isset($data['failed']) ? (int) $data['failed'] : null,
            expired: isset($data['expired']) ? (int) $data['expired'] : null,
            released: isset($data['released']) ? (int) $data['released'] : null,
        );
    }
}
