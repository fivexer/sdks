<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Partial update of a team — absent fields keep their stored value. The `key` is immutable, because the derived routing tag would change under live matching.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class PatchTeam
{
    public function __construct(
        private ?string $name = null,
        private ?string $description = null,
        private ?string $color = null,
    ) {
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
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
        return Json::compact([
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
        ]);
    }
}
