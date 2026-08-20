<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One time bucket of historical throughput; null latencies mean no data.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class StatsTimeseriesBucket
{
    public function __construct(
        public readonly string $bucketStart,
        public readonly int $completed,
        public readonly int $cancelled,
        public readonly ?float $avgWaitMs,
        public readonly ?float $p50WaitMs,
        public readonly ?float $p95WaitMs,
        public readonly ?float $avgHandleMs,
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
        );
    }
}
