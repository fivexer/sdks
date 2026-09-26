<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One worker's row in the team working-time record: shift and break time, and what happened to
 * the work offered to them, for the window.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamTimeWorker
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $label,
        public readonly int $shiftCount,
        /** Time on shift inside the window — breaks included, they happen on shift */
        public readonly int $onShiftMs,
        public readonly int $breakCount,
        public readonly int $breakMs,
        /** onShiftMs − breakMs: the worked-time figure */
        public readonly int $workingMs,
        /** A shift row with no end: on shift now, or never clocked out */
        public readonly bool $openShift,
        public readonly bool $openBreak,
        public readonly int $completed,
        public readonly int $offered,
        public readonly int $accepted,
        /** Offers this worker explicitly turned down */
        public readonly int $rejected,
        /** Offers that timed out unanswered while they held them — a missed response deadline */
        public readonly int $expired,
        /** Accepted work that ended in failure, including an SLA completion breach */
        public readonly int $failed,
        /** Pending work taken back by the idle sweep or an operator */
        public readonly int $released,
        /**
         * The raw log; present only when the query asked for entries.
         *
         * @var list<WorkerTimeEntry>|null
         */
        public readonly ?array $entries = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        /** @var list<WorkerTimeEntry>|null $entries */
        $entries = Json::parseEachOrNull($data, 'entries', [WorkerTimeEntry::class, 'fromArray']);
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            shiftCount: (int) ($data['shiftCount'] ?? 0),
            onShiftMs: (int) ($data['onShiftMs'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            breakMs: (int) ($data['breakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
            openShift: (bool) ($data['openShift'] ?? false),
            openBreak: (bool) ($data['openBreak'] ?? false),
            completed: (int) ($data['completed'] ?? 0),
            offered: (int) ($data['offered'] ?? 0),
            accepted: (int) ($data['accepted'] ?? 0),
            rejected: (int) ($data['rejected'] ?? 0),
            expired: (int) ($data['expired'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            released: (int) ($data['released'] ?? 0),
            entries: $entries,
        );
    }
}
