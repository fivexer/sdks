<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One evaluated candidate within a Decision. workerId is pulled out; every other
 * field (score, eligible, chosen, veto reasons, ...) is kept verbatim in detail -
 * reason kinds are additive over time, so unknown keys are forward-compatible.
 */
final class DecisionCandidate
{
    /**
     * @param array<string, mixed> $detail
     */
    public function __construct(
        public readonly ?string $workerId,
        public readonly array $detail,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $workerId = isset($data['workerId']) ? (string) $data['workerId'] : null;
        $detail = $data;
        unset($detail['workerId']);
        return new self($workerId, $detail);
    }
}
