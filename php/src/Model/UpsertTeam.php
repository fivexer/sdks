<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for creating a team. The server derives the routing `tag` from the key, so the key is what matching ultimately sees.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class UpsertTeam
{
    public function __construct(
        private readonly string $key,
        private readonly string $name,
        private ?string $description = null,
        private ?string $color = null,
    ) {
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;
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
            'key' => $this->key,
            'name' => $this->name,
        ] + Json::compact([
            'description' => $this->description,
            'color' => $this->color,
        ]);
    }
}
