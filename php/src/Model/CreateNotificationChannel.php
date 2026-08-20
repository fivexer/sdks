<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for creating a delivery channel.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class CreateNotificationChannel
{
    public function __construct(
        private readonly string $type,
        private readonly string $target,
        /** @var list<string> */
        private readonly array $events,
        /** write-only: never echoed back on a read */
        private ?string $secret = null,
    ) {
    }

    public function secret(string $secret): self
    {
        $this->secret = $secret;
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
            'type' => $this->type,
            'target' => $this->target,
            'events' => $this->events,
        ] + Json::compact([
            'secret' => $this->secret,
        ]);
    }
}
