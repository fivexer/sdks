<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * An opaque pointer to data behind your own API - stored and displayed, never fetched.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskReference
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly ?string $label,
        public readonly ?string $contentType,
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
            url: (string) ($data['url'] ?? ''),
            label: isset($data['label']) ? (string) $data['label'] : null,
            contentType: isset($data['contentType']) ? (string) $data['contentType'] : null,
        );
    }
}
