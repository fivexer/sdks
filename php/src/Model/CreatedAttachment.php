<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The attachment record plus where to PUT its bytes.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class CreatedAttachment
{
    public function __construct(
        public readonly Attachment $attachment,
        public readonly AttachmentUpload $upload,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attachment: Attachment::fromArray($data['attachment'] ?? []),
            upload: AttachmentUpload::fromArray($data['upload'] ?? []),
        );
    }
}
