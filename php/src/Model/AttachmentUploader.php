<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Who uploaded an attachment.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class AttachmentUploader
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $id,
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
        );
    }
}
