<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * What happens when the worker a task was matched to lets the response deadline run out.
 *
 * Without a policy the task is simply requeued after the workspace default and the same worker
 * may win it straight back — the failure mode `onNoResponse('block')` exists to stop. Escalation
 * owns the *response* clock only; the completion clock, shelf life and rejection budget belong
 * to {@see SlaPolicy}.
 */
final class EscalationPolicy
{
    /** @param int $respondWithinMs Milliseconds the matched worker has to respond (1s–24h) */
    public function __construct(
        public readonly int $respondWithinMs,
        /** 'block' stops the non-responder winning it back; 'allow' is the default */
        private ?string $onNoResponse = null,
        /** Added to the task's priority on every escalation, so an aging task outranks fresh work */
        private ?float $priorityBoost = null,
        /** @var list<list<string>>|null Tag sets to widen to, one rung per escalation */
        private ?array $tiers = null,
        private ?int $maxEscalations = null,
        /** Where an exhausted ladder leaves the task: 'queue' or 'park' */
        private ?string $onExhausted = null,
    ) {
    }

    public function onNoResponse(string $onNoResponse): self
    {
        $this->onNoResponse = $onNoResponse;
        return $this;
    }

    public function priorityBoost(float $priorityBoost): self
    {
        $this->priorityBoost = $priorityBoost;
        return $this;
    }

    /** @param list<list<string>> $tiers */
    public function tiers(array $tiers): self
    {
        $this->tiers = $tiers;
        return $this;
    }

    public function maxEscalations(int $maxEscalations): self
    {
        $this->maxEscalations = $maxEscalations;
        return $this;
    }

    public function onExhausted(string $onExhausted): self
    {
        $this->onExhausted = $onExhausted;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = Json::compact([
            'onNoResponse' => $this->onNoResponse,
            'priorityBoost' => $this->priorityBoost,
            'tiers' => $this->tiers,
            'maxEscalations' => $this->maxEscalations,
            'onExhausted' => $this->onExhausted,
        ]);
        $payload['respondWithinMs'] = $this->respondWithinMs;
        return $payload;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<list<string>>|null $tiers */
        $tiers = isset($data['tiers']) && \is_array($data['tiers']) ? $data['tiers'] : null;
        return new self(
            (int) ($data['respondWithinMs'] ?? 0),
            isset($data['onNoResponse']) ? (string) $data['onNoResponse'] : null,
            isset($data['priorityBoost']) ? (float) $data['priorityBoost'] : null,
            $tiers,
            isset($data['maxEscalations']) ? (int) $data['maxEscalations'] : null,
            isset($data['onExhausted']) ? (string) $data['onExhausted'] : null,
        );
    }
}
