<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * An attachment record; `status` is `pending` until the upload is confirmed.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class Attachment
{
    public function __construct(
        public readonly string $id,
        public readonly string $taskId,
        public readonly string $filename,
        public readonly string $contentType,
        public readonly int $sizeBytes,
        public readonly string $status,
        public readonly AttachmentUploader $uploader,
        public readonly int $createdAt,
        public readonly ?int $confirmedAt,
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
            filename: (string) ($data['filename'] ?? ''),
            contentType: (string) ($data['contentType'] ?? ''),
            sizeBytes: (int) ($data['sizeBytes'] ?? 0),
            status: (string) ($data['status'] ?? ''),
            uploader: AttachmentUploader::fromArray($data['uploader'] ?? []),
            createdAt: (int) ($data['createdAt'] ?? 0),
            confirmedAt: isset($data['confirmedAt']) ? (int) $data['confirmedAt'] : null,
        );
    }
}
