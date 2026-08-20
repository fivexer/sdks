<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Workspace-wide break rollup.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkspaceBreakMetrics
{
    public function __construct(
        /** @var list<WorkerBreakMetric> */
        public readonly array $workers,
        public readonly int $totalBreakMs,
        public readonly int $breakCount,
        public readonly int $activeCount,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workers: Json::parseEach($data, 'workers', [WorkerBreakMetric::class, 'fromArray']),
            totalBreakMs: (int) ($data['totalBreakMs'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            activeCount: (int) ($data['activeCount'] ?? 0),
        );
    }
}
