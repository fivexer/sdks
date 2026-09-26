<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The work an offboarding would send back to the queue, by state.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerOffboardingTasks
{
    public function __construct(
        public readonly int $pending,
        public readonly int $accepted,
        /** Appointments reserved for them */
        public readonly int $booked,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pending: (int) ($data['pending'] ?? 0),
            accepted: (int) ($data['accepted'] ?? 0),
            booked: (int) ($data['booked'] ?? 0),
        );
    }
}
