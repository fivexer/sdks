<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A stretch of a worker's time that stands against an appointment: an approved absence that
 * refuses a booking, or a soft conflict (outside a rostered shift, a busy personal calendar)
 * that allows it and is recorded on it. Times are epoch-milliseconds.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SlotConflict
{
    public function __construct(
        public readonly int $from,
        public readonly int $to,
        public readonly string $reason,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: (int) ($data['from'] ?? 0),
            to: (int) ($data['to'] ?? 0),
            reason: (string) ($data['reason'] ?? ''),
        );
    }
}
