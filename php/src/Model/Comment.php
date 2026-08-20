<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A comment on a task.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class Comment
{
    public function __construct(
        public readonly string $id,
        public readonly string $taskId,
        public readonly CommentAuthor $author,
        public readonly string $body,
        public readonly int $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            taskId: (string) ($data['taskId'] ?? ''),
            author: CommentAuthor::fromArray($data['author'] ?? []),
            body: (string) ($data['body'] ?? ''),
            createdAt: (int) ($data['createdAt'] ?? 0),
        );
    }
}
