<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * An unpaginated task listing — the parked and scheduled views return the whole set.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskList
{
    public function __construct(
        /** @var list<Task> */
        public readonly array $tasks,
        public readonly int $count,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tasks: Json::parseEach($data, 'tasks', [Task::class, 'fromArray']),
            count: (int) ($data['count'] ?? 0),
        );
    }
}
