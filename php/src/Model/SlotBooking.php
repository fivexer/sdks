<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A reservation of one worker's time for one task's appointment. Times are epoch-milliseconds.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SlotBooking
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $workerId,
        public readonly int $startAt,
        public readonly int $endAt,
        /** When the reservation was made */
        public readonly int $bookedAt,
        /** 'sweep' booked it automatically; 'manual' is a planner's own call */
        public readonly string $source,
        /**
         * Soft conflicts the booking was made over, so a planner reviewing the week sees which
         * bookings are worth a second look. An approved absence is never here: it refuses the
         * booking instead. Empty when there were none.
         *
         * @var list<SlotConflict>
         */
        public readonly array $warnings = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: (string) ($data['taskId'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            startAt: (int) ($data['startAt'] ?? 0),
            endAt: (int) ($data['endAt'] ?? 0),
            bookedAt: (int) ($data['bookedAt'] ?? 0),
            source: (string) ($data['source'] ?? ''),
            warnings: Json::parseEach($data, 'warnings', [SlotConflict::class, 'fromArray']),
        );
    }
}
