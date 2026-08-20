<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A cursor-paginated page of comments.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class CommentPage
{
    public function __construct(
        /** @var list<Comment> */
        public readonly array $comments,
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
            comments: Json::parseEach($data, 'comments', [Comment::class, 'fromArray']),
            nextCursor: isset($data['nextCursor']) ? (string) $data['nextCursor'] : null,
            hasMore: (bool) ($data['hasMore'] ?? false),
        );
    }
}
