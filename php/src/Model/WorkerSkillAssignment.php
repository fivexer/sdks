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
        private ?string $validFrom = null,
        private ?string $validUntil = null,
    ) {
    }

    /** Each distinguishes "leave the date alone" from "erase it" — both look like null. */
    private bool $clearingValidFrom = false;
    private bool $clearingValidUntil = false;

    public function weightOverride(float $weightOverride): self
    {
        $this->weightOverride = $weightOverride;
        return $this;
    }

    /** Inclusive ISO day (`YYYY-MM-DD`) the qualification becomes valid. */
    public function validFrom(string $validFrom): self
    {
        $this->validFrom = $validFrom;
        $this->clearingValidFrom = false;
        return $this;
    }

    /** Erase a stored start date, making the qualification always held. Sends null. */
    public function clearValidFrom(): self
    {
        $this->validFrom = null;
        $this->clearingValidFrom = true;
        return $this;
    }

    /**
     * Inclusive *last* day it may be relied on. The roster checks this against the **shift's**
     * date, so a licence lapsing mid-period bars the shifts after it and leaves the earlier
     * ones standing.
     */
    public function validUntil(string $validUntil): self
    {
        $this->validUntil = $validUntil;
        $this->clearingValidUntil = false;
        return $this;
    }

    /** Erase a stored expiry, making the qualification permanent. Sends null. */
    public function clearValidUntil(): self
    {
        $this->validUntil = null;
        $this->clearingValidUntil = true;
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
            'validFrom' => $this->validFrom,
            'validUntil' => $this->validUntil,
        ])
            + ($this->clearingValidFrom ? ['validFrom' => null] : [])
            + ($this->clearingValidUntil ? ['validUntil' => null] : []);
    }
}
