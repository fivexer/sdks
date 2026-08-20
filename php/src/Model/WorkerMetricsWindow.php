<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A worker's own recent throughput. Medians are null for a worker with no history, not zero.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetricsWindow
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $since,
        /** @var list<WorkerMetricsWindowDay> */
        public readonly array $days,
        public readonly ?int $medianWaitMs,
        public readonly ?int $medianCycleMs,
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
            since: (string) ($data['since'] ?? ''),
            days: Json::parseEach($data, 'days', [WorkerMetricsWindowDay::class, 'fromArray']),
            medianWaitMs: isset($data['medianWaitMs']) ? (int) $data['medianWaitMs'] : null,
            medianCycleMs: isset($data['medianCycleMs']) ? (int) $data['medianCycleMs'] : null,
        );
    }
}
