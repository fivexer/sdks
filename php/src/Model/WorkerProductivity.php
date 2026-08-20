<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker throughput over a window.
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
        );
    }
}
