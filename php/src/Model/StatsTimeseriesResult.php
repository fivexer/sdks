<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Historical throughput over a window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class StatsTimeseriesResult
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $bucket,
        /** @var list<StatsTimeseriesBucket> */
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
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            bucket: (string) ($data['bucket'] ?? ''),
            buckets: Json::parseEach($data, 'buckets', [StatsTimeseriesBucket::class, 'fromArray']),
        );
    }
}
