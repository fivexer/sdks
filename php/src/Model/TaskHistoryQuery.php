<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The filter on `tasks()->history()`. Every field is optional; an empty query returns every
 * finished task, newest first.
 *
 * A model rather than method arguments because there are eight filters, and positional nulls
 * that long read worse than the builder.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class TaskHistoryQuery
{
    public function __construct(
        /**
         * One terminal status or several: 'completed', 'cancelled', 'failed', 'expired'.
         * Several travel as one comma-joined parameter; empty means all of them.
         *
         * @var list<string>|null
         */
        private ?array $status = null,
        private ?string $workerId = null,
        /** One tag the task carried */
        private ?string $tag = null,
        /** ISO-8601, inclusive — the window is on the finish time */
        private ?string $from = null,
        /** ISO-8601, exclusive */
        private ?string $to = null,
        /** Substring of the title or task id, case-insensitive; ignored below two characters */
        private ?string $q = null,
        private ?string $cursor = null,
        /** Up to 100; 50 by default */
        private ?int $limit = null,
    ) {
    }

    /** One terminal status or several. */
    public function status(string ...$status): self
    {
        $this->status = \array_values($status);
        return $this;
    }

    public function workerId(string $workerId): self
    {
        $this->workerId = $workerId;
        return $this;
    }

    public function tag(string $tag): self
    {
        $this->tag = $tag;
        return $this;
    }

    /** ISO-8601 start of the finish-time window, inclusive. */
    public function from(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    /** ISO-8601 end of the finish-time window, exclusive. */
    public function to(string $to): self
    {
        $this->to = $to;
        return $this;
    }

    public function q(string $q): self
    {
        $this->q = $q;
        return $this;
    }

    /** The previous page's nextCursor. */
    public function cursor(string $cursor): self
    {
        $this->cursor = $cursor;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * The query parameters, nulls included — the transport drops them. The status list is
     * joined into the single comma-separated value the wire takes.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(): array
    {
        return [
            'status' => ($this->status === null || $this->status === []) ? null : \implode(',', $this->status),
            'workerId' => $this->workerId,
            'tag' => $this->tag,
            'from' => $this->from,
            'to' => $this->to,
            'q' => $this->q,
            'cursor' => $this->cursor,
            'limit' => $this->limit,
        ];
    }
}
