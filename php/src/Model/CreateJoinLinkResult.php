<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A new join link plus its URL. The URL is returned only here — a lost link is re-created, never recovered.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class CreateJoinLinkResult
{
    public function __construct(
        public readonly JoinLink $link,
        public readonly string $joinUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            link: JoinLink::fromArray($data['link'] ?? []),
            joinUrl: (string) ($data['joinUrl'] ?? ''),
        );
    }
}
