<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One entry of a bulk feedback submission. Send signals, a reward, or both.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class LearningFeedbackItem
{
    public function __construct(
        private readonly string $taskId,
        /** @var array<string, float> */
        private ?array $signals = null,
        private ?float $reward = null,
    ) {
    }

    /** @param array<string, float> $signals */
    public function signals(array $signals): self
    {
        $this->signals = $signals;
        return $this;
    }

    public function reward(float $reward): self
    {
        $this->reward = $reward;
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
            'taskId' => $this->taskId,
        ] + Json::compact([
            'signals' => $this->signals,
            'reward' => $this->reward,
        ]);
    }
}
