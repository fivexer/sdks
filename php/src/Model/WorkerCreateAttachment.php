<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Worker-plane upload input.
 *
 * The same shape as {@see CreateAttachment} minus `workerId`: on this plane the uploader is
 * derived from the session, so a worker cannot attribute a file to anyone else.
 */
final class WorkerCreateAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $contentType,
        public readonly int $sizeBytes,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'contentType' => $this->contentType,
            'sizeBytes' => $this->sizeBytes,
        ];
    }
}
