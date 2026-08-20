<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A short-lived download URL.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class AttachmentDownload
{
    public function __construct(
        public readonly string $url,
        public readonly int $expiresAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string) ($data['url'] ?? ''),
            expiresAt: (int) ($data['expiresAt'] ?? 0),
        );
    }
}
