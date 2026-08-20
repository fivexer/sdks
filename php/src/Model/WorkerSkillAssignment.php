<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Assign a skill to a worker. `level` is 1-5; the API validates the range.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class WorkerSkillAssignment
{
    public function __construct(
        private readonly string $skillId,
        private readonly int $level,
        private ?float $weightOverride = null,
    ) {
    }

    public function weightOverride(float $weightOverride): self
    {
        $this->weightOverride = $weightOverride;
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
            'skillId' => $this->skillId,
            'level' => $this->level,
        ] + Json::compact([
            'weightOverride' => $this->weightOverride,
        ]);
    }
}
