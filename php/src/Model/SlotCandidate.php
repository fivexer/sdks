<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One worker weighed for one appointment.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SlotCandidate
{
    public function __construct(
        public readonly string $workerId,
        /** Match score, as the readiness check reports it */
        public readonly float $score,
        /** Base priority plus score and any geo/learning boost — what ranks them */
        public readonly float $effectivePriority,
        /** Whether a booking for this worker would go through right now */
        public readonly bool $bookable,
        /**
         * Why they are or are not eligible, in the vocabulary the decision traces use — a skill
         * gate, a veto, a prior hand-back.
         *
         * @var list<array<string, mixed>>
         */
        public readonly array $reasons = [],
        /** An appointment already on them that overlaps this one */
        public readonly ?string $clashingTaskId = null,
        /**
         * Approved absences covering the slot. These refuse a booking.
         *
         * @var list<SlotConflict>
         */
        public readonly array $blocked = [],
        /**
         * Soft conflicts. These allow the booking, and are recorded on it.
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
            workerId: (string) ($data['workerId'] ?? ''),
            score: (float) ($data['score'] ?? 0),
            effectivePriority: (float) ($data['effectivePriority'] ?? 0),
            bookable: (bool) ($data['bookable'] ?? false),
            reasons: \array_values($data['reasons'] ?? []),
            clashingTaskId: isset($data['clashingTaskId']) ? (string) $data['clashingTaskId'] : null,
            blocked: Json::parseEach($data, 'blocked', [SlotConflict::class, 'fromArray']),
            warnings: Json::parseEach($data, 'warnings', [SlotConflict::class, 'fromArray']),
        );
    }
}
