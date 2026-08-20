<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A worker's location after a mobile update.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerLocationResult
{
    public function __construct(
        public readonly string $workerId,
        public readonly float $latitude,
        public readonly float $longitude,
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
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
        );
    }
}
