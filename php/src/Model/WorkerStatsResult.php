<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Per-worker throughput over a window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerStatsResult
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        /** @var list<WorkerProductivity> */
        public readonly array $workers,
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
            workers: Json::parseEach($data, 'workers', [WorkerProductivity::class, 'fromArray']),
        );
    }
}
