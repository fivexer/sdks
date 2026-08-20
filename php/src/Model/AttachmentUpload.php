<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A presigned upload target. Send `headers` exactly as given - they are signed.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class AttachmentUpload
{
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        /** @var array<string, string> */
        public readonly array $headers,
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
            method: (string) ($data['method'] ?? 'PUT'),
            headers: $data['headers'] ?? [],
            expiresAt: (int) ($data['expiresAt'] ?? 0),
        );
    }
}
