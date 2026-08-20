<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for starting a workflow run.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class StartRun
{
    public function __construct(
        /** @var array<string, mixed> */
        private ?array $context = null,
        /** required when a step assigns to the initiator */
        private ?string $initiatorWorkerId = null,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function context(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function initiatorWorkerId(string $initiatorWorkerId): self
    {
        $this->initiatorWorkerId = $initiatorWorkerId;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Json::compact([
            'context' => $this->context,
            'initiatorWorkerId' => $this->initiatorWorkerId,
        ]);
        return $payload;
    }
}
