<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The totals of a worker's time log for a window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTimeTotals
{
    public function __construct(
        public readonly int $shiftCount,
        /** Time on shift, breaks included. */
        public readonly int $onShiftMs,
        public readonly int $breakCount,
        public readonly int $breakMs,
        /** onShiftMs − breakMs: breaks are time inside a shift, not beside it. */
        public readonly int $workingMs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
        );
    }
}
