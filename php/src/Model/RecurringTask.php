<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A standing template with its clock, from `$client->tasks()->recurring()->list()`.
 *
 * The template is never matchable and never appears in `tasks()->list()` or the queue stats —
 * only the occurrences cut from it do.
 */
final class RecurringTask
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly string $id,
        public readonly array $tags,
        public readonly RecurrencePolicy $recurrence,
        /** Epoch ms the next occurrence's window opens */
        public readonly int $nextAt,
        /** Occurrences materialized so far */
        public readonly int $occurrences,
        public readonly ?float $priority = null,
        public readonly ?string $title = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $tags */
        $tags = isset($data['tags']) && \is_array($data['tags']) ? \array_values($data['tags']) : [];
        /** @var array<string, mixed> $recurrence */
        $recurrence = isset($data['recurrence']) && \is_array($data['recurrence']) ? $data['recurrence'] : [];
        return new self(
            (string) ($data['id'] ?? ''),
            $tags,
            RecurrencePolicy::fromArray($recurrence),
            (int) ($data['nextAt'] ?? 0),
            (int) ($data['occurrences'] ?? 0),
            isset($data['priority']) ? (float) $data['priority'] : null,
            isset($data['title']) ? (string) $data['title'] : null,
        );
    }
}
