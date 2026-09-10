<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A skill as held by a worker, with the effective routing weight it contributes.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerSkill
{
    public function __construct(
        public readonly string $skillId,
        public readonly string $key,
        public readonly string $name,
        public readonly int $level,
        public readonly float $weight,
        public readonly ?float $weightOverride,
        /** Inclusive ISO day it became valid; null when unbounded. */
        public readonly ?string $validFrom = null,
        /** Inclusive *last* day it may be relied on; null when it does not expire. */
        public readonly ?string $validUntil = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            skillId: (string) ($data['skillId'] ?? ''),
            key: (string) ($data['key'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            level: (int) ($data['level'] ?? 0),
            weight: (float) ($data['weight'] ?? 0),
            weightOverride: isset($data['weightOverride']) ? (float) $data['weightOverride'] : null,
            validFrom: isset($data['validFrom']) ? (string) $data['validFrom'] : null,
            validUntil: isset($data['validUntil']) ? (string) $data['validUntil'] : null,
        );
    }
}
