<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One worker's bucketed history — the per-worker twin of the workspace timeseries.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTimeseriesResult
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $bucket,
        /** @var list<WorkerTimeseriesBucket> */
        public readonly array $buckets,
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
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            bucket: (string) ($data['bucket'] ?? ''),
            buckets: Json::parseEach($data, 'buckets', [WorkerTimeseriesBucket::class, 'fromArray']),
        );
    }
}
