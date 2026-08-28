<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker's recorded time and throughput: today, plus a rolling window.
 *
 * The operator-plane mirror of what the worker sees in their own portal — the same numbers on
 * both planes, deliberately, since worked time comes from the one shift log.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetrics
{
    public function __construct(
        public readonly string $workerId,
        public readonly WorkerMetricsDay $today,
        public readonly WorkerMetricsPeriod $window,
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
            today: WorkerMetricsDay::fromArray($data['today'] ?? []),
            window: WorkerMetricsPeriod::fromArray($data['window'] ?? []),
        );
    }
}
