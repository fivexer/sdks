<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * SLO counters. `acceptanceRate` is null until at least one offer is recorded — 0.0 ("everyone missed") and null ("nothing measured") are different answers.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SlaStats
{
    public function __construct(
        public readonly ?string $tag,
        public readonly int $offers,
        public readonly int $acceptedInTime,
        public readonly int $acceptanceBreaches,
        public readonly int $completionBreaches,
        public readonly int $ttlExpiries,
        public readonly int $rejectionParked,
        public readonly int $scheduleMisses,
        public readonly float $meanAcceptLatencyMs,
        public readonly float $meanCompleteLatencyMs,
        /** Null until at least one offer is recorded. */
        public readonly ?float $acceptanceRate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tag: isset($data['tag']) ? (string) $data['tag'] : null,
            offers: (int) ($data['offers'] ?? 0),
            acceptedInTime: (int) ($data['acceptedInTime'] ?? 0),
            acceptanceBreaches: (int) ($data['acceptanceBreaches'] ?? 0),
            completionBreaches: (int) ($data['completionBreaches'] ?? 0),
            ttlExpiries: (int) ($data['ttlExpiries'] ?? 0),
            rejectionParked: (int) ($data['rejectionParked'] ?? 0),
            scheduleMisses: (int) ($data['scheduleMisses'] ?? 0),
            meanAcceptLatencyMs: (float) ($data['meanAcceptLatencyMs'] ?? 0),
            meanCompleteLatencyMs: (float) ($data['meanCompleteLatencyMs'] ?? 0),
            acceptanceRate: isset($data['acceptanceRate']) ? (float) $data['acceptanceRate'] : null,
        );
    }
}
