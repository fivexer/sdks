<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One break inside a shift that is being corrected or recorded after the fact.
 *
 * On a correction ({@see CorrectTimeEntryInput::breaks()}) a break with an id is that break,
 * moved or left alone; one without an id is new; and a null end leaves it open. On a created
 * shift ({@see CreateTimeEntryInput::breaks()}) both ends are required and there is no id.
 */
final class TimeEntryBreakInput
{
    public function __construct(
        /** ISO-8601 */
        private readonly string $startedAt,
        /** ISO-8601; null leaves the break open (corrections only) */
        private readonly ?string $endedAt,
        /** The existing break this row stands for; omit for a new one */
        private readonly ?string $id = null,
    ) {
    }

    /**
     * Serialise for the wire. `endedAt` is always sent — a null end is the statement "still
     * open", not an unset field.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = $this->id === null ? [] : ['id' => $this->id];
        return $payload + [
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
        ];
    }
}
