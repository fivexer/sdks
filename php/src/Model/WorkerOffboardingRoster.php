<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A roster with shifts after the worker's last day naming them — shifts an offboarding frees.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerOffboardingRoster
{
    public function __construct(
        public readonly string $rosterId,
        public readonly string $name,
        public readonly bool $published,
        public readonly int $shifts,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rosterId: (string) ($data['rosterId'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            published: (bool) ($data['published'] ?? false),
            shifts: (int) ($data['shifts'] ?? 0),
        );
    }
}
