<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The window and scope for `team()->time()`. Every field is optional.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class TeamTimeQuery
{
    public function __construct(
        /** ISO-8601 */
        private ?string $from = null,
        /** ISO-8601 */
        private ?string $to = null,
        private ?string $teamId = null,
        private ?string $workerId = null,
        /** Fetch the raw shift/break log too — needed for per-worker entries and the day series */
        private bool $entries = false,
    ) {
    }

    public function from(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;
        return $this;
    }

    /** Narrow the report to one crew. */
    public function teamId(string $teamId): self
    {
        $this->teamId = $teamId;
        return $this;
    }

    public function workerId(string $workerId): self
    {
        $this->workerId = $workerId;
        return $this;
    }

    /** Also return each worker's shift/break log and the per-day series derived from it. */
    public function entries(bool $entries = true): self
    {
        $this->entries = $entries;
        return $this;
    }

    /**
     * The query parameters, nulls included — the transport drops them. `entries` is sent only
     * when asked for.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'teamId' => $this->teamId,
            'workerId' => $this->workerId,
            'entries' => $this->entries ? 'true' : null,
        ];
    }
}
