<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** Result of GET /v1/workers - the list of worker ids and the count. */
final class WorkerList
{
    /** @param list<string> $workers */
    public function __construct(
        public readonly array $workers,
        public readonly int $count,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            workers: \array_map('strval', $data['workers'] ?? []),
            count: (int) ($data['count'] ?? 0),
        );
    }
}
