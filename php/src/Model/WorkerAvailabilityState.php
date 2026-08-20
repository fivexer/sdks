<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A worker's own shift switch, after flipping it.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerAvailabilityState
{
    public function __construct(
        public readonly string $workerId,
        public readonly bool $available,
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
            available: (bool) ($data['available'] ?? false),
        );
    }
}
