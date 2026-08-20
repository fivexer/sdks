<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for reserving an attachment record.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class CreateAttachment
{
    public function __construct(
        private readonly string $filename,
        private readonly string $contentType,
        private readonly int $sizeBytes,
        /** attribute the upload to a worker (API-key callers only) */
        private ?string $workerId = null,
    ) {
    }

    public function workerId(string $workerId): self
    {
        $this->workerId = $workerId;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'contentType' => $this->contentType,
            'sizeBytes' => $this->sizeBytes,
        ] + Json::compact([
            'workerId' => $this->workerId,
        ]);
    }
}
