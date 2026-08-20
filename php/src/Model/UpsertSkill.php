<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for defining a skill.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class UpsertSkill
{
    public function __construct(
        private readonly string $key,
        private readonly string $name,
        private ?string $description = null,
    ) {
    }

    public function description(string $description): self
    {
        $this->description = $description;
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
        ]);
    }
}
