<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Record a shift that was worked and never logged. Both ends are stated: an open record here
 * would be indistinguishable from a running shift, and would then block the very period close it
 * exists to make possible. A closed period refuses it.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class CreateTimeEntryInput
{
    public function __construct(
        /** ISO-8601 */
        private readonly string $startedAt,
        /** ISO-8601 */
        private readonly string $endedAt,
        /** Why it is being recorded after the fact — mandatory, and kept on the trail */
        private readonly string $note,
        /** @var list<TimeEntryBreakInput>|null */
        private ?array $breaks = null,
        private ?string $description = null,
        private ?string $taskId = null,
    ) {
    }

    /**
     * Breaks taken inside the shift, both ends stated. Needs a worker with a portal login —
     * breaks are filed under it.
     *
     * @param list<TimeEntryBreakInput> $breaks
     */
    public function breaks(array $breaks): self
    {
        $this->breaks = $breaks;
        return $this;
    }

    /** A note describing the work the shift represents. */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /** The task this time was spent on. */
    public function taskId(string $taskId): self
    {
        $this->taskId = $taskId;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'note' => $this->note,
        ] + Json::compact([
            'breaks' => Json::each($this->breaks),
            'description' => $this->description,
            'taskId' => $this->taskId,
        ]);
    }
}
