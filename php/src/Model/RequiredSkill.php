<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A minimum skill level a candidate must hold.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class RequiredSkill
{
    public function __construct(
        private readonly string $skillId,
        private readonly int $minLevel,
    ) {
    }


    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'skillId' => $this->skillId,
            'minLevel' => $this->minLevel,
        ] + Json::compact([

        ]);
    }
}
