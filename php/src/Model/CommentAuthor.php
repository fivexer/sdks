<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * `user` = console human, `worker` = on behalf of a worker, `api` = the integration.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class CommentAuthor
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $id,
        public readonly ?string $label,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? ''),
            id: isset($data['id']) ? (string) $data['id'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }
}
