<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for adding a comment to a task.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class AddComment
{
    public function __construct(
        private readonly string $body,
        /** attribute the comment to a worker (API-key callers only) */
        private ?string $workerId = null,
        private ?string $authorLabel = null,
    ) {
    }

    public function workerId(string $workerId): self
    {
        $this->workerId = $workerId;
        return $this;
    }

    public function authorLabel(string $authorLabel): self
    {
        $this->authorLabel = $authorLabel;
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
            'body' => $this->body,
        ] + Json::compact([
            'workerId' => $this->workerId,
            'authorLabel' => $this->authorLabel,
        ]);
    }
}
