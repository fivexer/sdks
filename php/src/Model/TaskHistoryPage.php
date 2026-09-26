<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A page of finished tasks, newest first. Pass nextCursor back as the next query's cursor while
 * hasMore is true.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskHistoryPage
{
    public function __construct(
        /** @var list<ArchivedTask> */
        public readonly array $tasks,
        /** Opaque; null on the last page */
        public readonly ?string $nextCursor,
        public readonly bool $hasMore,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tasks: Json::parseEach($data, 'tasks', [ArchivedTask::class, 'fromArray']),
            nextCursor: isset($data['nextCursor']) ? (string) $data['nextCursor'] : null,
            hasMore: (bool) ($data['hasMore'] ?? false),
        );
    }
}
