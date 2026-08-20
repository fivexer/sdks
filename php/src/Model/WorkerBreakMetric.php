<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker break totals over a window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerBreakMetric
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $label,
        public readonly int $count,
        public readonly int $totalBreakMs,
        public readonly int $longestBreakMs,
        public readonly bool $active,
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
            label: (string) ($data['label'] ?? ''),
            count: (int) ($data['count'] ?? 0),
            totalBreakMs: (int) ($data['totalBreakMs'] ?? 0),
            longestBreakMs: (int) ($data['longestBreakMs'] ?? 0),
            active: (bool) ($data['active'] ?? false),
        );
    }
}
