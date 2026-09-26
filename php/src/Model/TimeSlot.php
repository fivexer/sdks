<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A task's appointment: when the work is performed and for how long.
 *
 * A different clock from {@see SchedulePolicy}, which says when a task may be handed out. A
 * slotted task is held out of matching until its slot; ahead of that the booking sweep reserves
 * a worker for it. The reservation blocks the hour on their calendar but takes no place in their
 * backlog and starts no deadline — at `startAt` the task reaches whoever holds the booking as an
 * ordinary pending offer.
 *
 * Used both as input ({@see CreateTask::slot()}) and read back on {@see Task::$slot}. Timestamps
 * are absolute epoch-milliseconds.
 */
final class TimeSlot
{
    public function __construct(
        /** Epoch ms at which the work is performed */
        private readonly int $startAt,
        /** How long it takes, in ms. At least a minute, at most 24 hours */
        private readonly int $durationMs,
        /** How far ahead of startAt a worker may be reserved; defaults to a week */
        private ?int $bookAheadMs = null,
        /** 'queue' (default) matches it late, 'park' holds it for an operator, 'drop' removes it */
        private ?string $onUnbooked = null,
    ) {
    }

    /**
     * How far ahead of the start a worker may be reserved. Earlier gives people more notice;
     * later sees a truer picture of who is free.
     */
    public function bookAheadMs(int $bookAheadMs): self
    {
        $this->bookAheadMs = $bookAheadMs;
        return $this;
    }

    /** What happens if the start arrives with nobody booked: 'queue', 'park' or 'drop'. */
    public function onUnbooked(string $onUnbooked): self
    {
        $this->onUnbooked = $onUnbooked;
        return $this;
    }

    public function getStartAt(): int
    {
        return $this->startAt;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getBookAheadMs(): ?int
    {
        return $this->bookAheadMs;
    }

    public function getOnUnbooked(): ?string
    {
        return $this->onUnbooked;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'startAt' => $this->startAt,
            'durationMs' => $this->durationMs,
        ] + Json::compact([
            'bookAheadMs' => $this->bookAheadMs,
            'onUnbooked' => $this->onUnbooked,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['startAt'] ?? 0),
            (int) ($data['durationMs'] ?? 0),
            isset($data['bookAheadMs']) ? (int) $data['bookAheadMs'] : null,
            isset($data['onUnbooked']) ? (string) $data['onUnbooked'] : null,
        );
    }
}
