<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A page of tasks from GET /v1/tasks. Use nextCursor with the next request's
 * cursor to paginate while hasMore is true.
 */
final class TaskPage
{
    /**
     * @param list<Task> $tasks
     */
    public function __construct(
        public readonly array $tasks,
        public readonly ?string $nextCursor,
        public readonly bool $hasMore,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tasks = \array_map(
            static fn(array $t): Task => Task::fromArray($t),
            $data['tasks'] ?? [],
        );
        return new self(
            tasks: $tasks,
            nextCursor: $data['nextCursor'] ?? null,
            hasMore: (bool) ($data['hasMore'] ?? false),
        );
    }
}
