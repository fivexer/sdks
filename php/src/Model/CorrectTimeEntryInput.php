<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Change one recorded shift or break. The reason is not optional: the record is the employer's
 * obligation (CJEU C-55/18), so a change to it keeps what it said before and says why.
 *
 * Patch semantics: a field never set is left alone. {@see self::reopen()},
 * {@see self::clearDescription()} and {@see self::clearTaskId()} send an explicit null, which a
 * PHP null cannot say on its own.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class CorrectTimeEntryInput
{
    public function __construct(
        /** Why the record is being changed — mandatory, and kept on the trail */
        private readonly string $note,
        /** ISO-8601 */
        private ?string $startedAt = null,
        /** ISO-8601 */
        private ?string $endedAt = null,
        /** 'shift' or 'break' — saves a lookup when the caller already knows */
        private ?string $type = null,
        /** @var list<TimeEntryBreakInput>|null */
        private ?array $breaks = null,
        private ?string $description = null,
        private ?string $taskId = null,
    ) {
    }

    /**
     * Fields to send as an explicit null, keyed by wire name.
     *
     * @var array<string, true>
     */
    private array $cleared = [];

    public function startedAt(string $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function endedAt(string $endedAt): self
    {
        $this->endedAt = $endedAt;
        unset($this->cleared['endedAt']);
        return $this;
    }

    /** Send `endedAt: null` — reopen a shift closed by mistake while somebody kept working. */
    public function reopen(): self
    {
        $this->endedAt = null;
        $this->cleared['endedAt'] = true;
        return $this;
    }

    /** 'shift' or 'break'. */
    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Shifts only: every break the shift holds afterwards, judged and saved with it in one
     * transaction. A break inside the shift that is not listed is removed; never calling this
     * leaves the breaks as they are.
     *
     * @param list<TimeEntryBreakInput> $breaks
     */
    public function breaks(array $breaks): self
    {
        $this->breaks = $breaks;
        return $this;
    }

    /** Shifts only: the work note the record carries. */
    public function description(string $description): self
    {
        $this->description = $description;
        unset($this->cleared['description']);
        return $this;
    }

    /** Remove the work note. Sends null. */
    public function clearDescription(): self
    {
        $this->description = null;
        $this->cleared['description'] = true;
        return $this;
    }

    /** Shifts only: the task this time was spent on. */
    public function taskId(string $taskId): self
    {
        $this->taskId = $taskId;
        unset($this->cleared['taskId']);
        return $this;
    }

    /** Detach the task. Sends null. */
    public function clearTaskId(): self
    {
        $this->taskId = null;
        $this->cleared['taskId'] = true;
        return $this;
    }

    /**
     * Serialise for the wire: unset fields are omitted, cleared ones are sent as null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['note' => $this->note] + Json::compact([
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'type' => $this->type,
            'breaks' => Json::each($this->breaks),
            'description' => $this->description,
            'taskId' => $this->taskId,
        ]);
        foreach (\array_keys($this->cleared) as $field) {
            $payload[$field] = null;
        }
        return $payload;
    }
}
