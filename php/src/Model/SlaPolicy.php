<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The completion clock, the shelf life and the rejection budget.
 *
 * Complements {@see EscalationPolicy} rather than overlapping it: escalation owns the *response*
 * clock, SLA owns everything after. An SLA never gates matching eligibility — a breach is
 * reported and acted on, it does not make the task unmatchable.
 */
final class SlaPolicy
{
    public function __construct(
        /** Completion deadline, measured from acceptance. Breaches fire once */
        private ?int $completeWithinMs = null,
        /** Shelf life from first enqueue — never extended by a requeue */
        private ?int $expireAfterMs = null,
        /** Rejections allowed before onMaxRejections; outranks the escalation ladder */
        private ?int $maxRejections = null,
        /** 'notify' | 'requeue' | 'fail' | 'park' */
        private ?string $onCompletionBreach = null,
        /** 'park' | 'fail' | 'keep' */
        private ?string $onMaxRejections = null,
        /** 'drop' | 'park' */
        private ?string $onExpire = null,
    ) {
    }

    public function completeWithinMs(int $completeWithinMs): self
    {
        $this->completeWithinMs = $completeWithinMs;
        return $this;
    }

    public function expireAfterMs(int $expireAfterMs): self
    {
        $this->expireAfterMs = $expireAfterMs;
        return $this;
    }

    public function maxRejections(int $maxRejections): self
    {
        $this->maxRejections = $maxRejections;
        return $this;
    }

    public function onCompletionBreach(string $onCompletionBreach): self
    {
        $this->onCompletionBreach = $onCompletionBreach;
        return $this;
    }

    public function onMaxRejections(string $onMaxRejections): self
    {
        $this->onMaxRejections = $onMaxRejections;
        return $this;
    }

    public function onExpire(string $onExpire): self
    {
        $this->onExpire = $onExpire;
        return $this;
    }

    public function getCompleteWithinMs(): ?int
    {
        return $this->completeWithinMs;
    }

    public function getExpireAfterMs(): ?int
    {
        return $this->expireAfterMs;
    }

    public function getMaxRejections(): ?int
    {
        return $this->maxRejections;
    }

    public function getOnCompletionBreach(): ?string
    {
        return $this->onCompletionBreach;
    }

    public function getOnMaxRejections(): ?string
    {
        return $this->onMaxRejections;
    }

    public function getOnExpire(): ?string
    {
        return $this->onExpire;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::compact([
            'completeWithinMs' => $this->completeWithinMs,
            'expireAfterMs' => $this->expireAfterMs,
            'maxRejections' => $this->maxRejections,
            'onCompletionBreach' => $this->onCompletionBreach,
            'onMaxRejections' => $this->onMaxRejections,
            'onExpire' => $this->onExpire,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['completeWithinMs']) ? (int) $data['completeWithinMs'] : null,
            isset($data['expireAfterMs']) ? (int) $data['expireAfterMs'] : null,
            isset($data['maxRejections']) ? (int) $data['maxRejections'] : null,
            isset($data['onCompletionBreach']) ? (string) $data['onCompletionBreach'] : null,
            isset($data['onMaxRejections']) ? (string) $data['onMaxRejections'] : null,
            isset($data['onExpire']) ? (string) $data['onExpire'] : null,
        );
    }
}
