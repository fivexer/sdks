<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Which clocks to reset when returning a parked task to the queue. Leave a clock set and the next sweep may park the task straight back.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class UnparkTask
{
    public function __construct(
        private ?bool $resetEscalation = null,
        private ?bool $resetSla = null,
        private ?bool $resetSchedule = null,
    ) {
    }

    public function resetEscalation(bool $resetEscalation): self
    {
        $this->resetEscalation = $resetEscalation;
        return $this;
    }

    public function resetSla(bool $resetSla): self
    {
        $this->resetSla = $resetSla;
        return $this;
    }

    public function resetSchedule(bool $resetSchedule): self
    {
        $this->resetSchedule = $resetSchedule;
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
            'resetEscalation' => $this->resetEscalation,
            'resetSla' => $this->resetSla,
            'resetSchedule' => $this->resetSchedule,
        ]);
    }
}
