<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Current vs learned routing weights for one worker.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class PreviewedWorkerWeights
{
    public function __construct(
        public readonly string $workerId,
        /** @var array<string, float> */
        public readonly array $current,
        /** @var array<string, float> */
        public readonly array $learned,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            current: $data['current'] ?? [],
            learned: $data['learned'] ?? [],
        );
    }
}
