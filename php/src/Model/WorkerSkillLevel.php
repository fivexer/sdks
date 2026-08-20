<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One skill a worker claims. Levels are 1-5; the routing weight they project to is the server's business.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class WorkerSkillLevel
{
    public function __construct(
        private readonly string $skillId,
        /** 1-5 */
        private readonly int $level,
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
            'level' => $this->level,
        ];
    }
}
