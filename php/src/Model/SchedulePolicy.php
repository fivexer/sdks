<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * When a task may be offered.
 *
 * Unlike escalation and SLA there is no workspace default to inherit — these timestamps are
 * absolute epoch-milliseconds.
 */
final class SchedulePolicy
{
    public function __construct(
        /** Held out of matching until this moment; the task reads as 'scheduled' until then */
        private ?int $notBefore = null,
        /** The offer window closes here; an unserved task is parked or dropped */
        private ?int $notAfter = null,
        /** 'park' keeps a missed task for review, 'drop' discards it */
        private ?string $onMiss = null,
    ) {
    }

    public function notBefore(int $notBefore): self
    {
        $this->notBefore = $notBefore;
        return $this;
    }

    public function notAfter(int $notAfter): self
    {
        $this->notAfter = $notAfter;
        return $this;
    }

    public function onMiss(string $onMiss): self
    {
        $this->onMiss = $onMiss;
        return $this;
    }

    public function getNotBefore(): ?int
    {
        return $this->notBefore;
    }

    public function getNotAfter(): ?int
    {
        return $this->notAfter;
    }

    public function getOnMiss(): ?string
    {
        return $this->onMiss;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::compact([
            'notBefore' => $this->notBefore,
            'notAfter' => $this->notAfter,
            'onMiss' => $this->onMiss,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['notBefore']) ? (int) $data['notBefore'] : null,
            isset($data['notAfter']) ? (int) $data['notAfter'] : null,
            isset($data['onMiss']) ? (string) $data['onMiss'] : null,
        );
    }
}
