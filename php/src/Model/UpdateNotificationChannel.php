<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Partial channel update - absent fields keep their stored value.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class UpdateNotificationChannel
{
    public function __construct(
        private ?string $type = null,
        private ?string $target = null,
        /** @var list<string> */
        private ?array $events = null,
        private ?string $secret = null,
        private ?bool $disabled = null,
    ) {
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function target(string $target): self
    {
        $this->target = $target;
        return $this;
    }

    /** @param list<string> $events */
    public function events(array $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function secret(string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    public function disabled(bool $disabled): self
    {
        $this->disabled = $disabled;
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
            'type' => $this->type,
            'target' => $this->target,
            'events' => $this->events,
            'secret' => $this->secret,
            'disabled' => $this->disabled,
        ]);
        return $payload;
    }
}
