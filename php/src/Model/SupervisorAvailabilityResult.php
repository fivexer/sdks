<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Outcome of pausing or resuming a crew member. `releasedTaskIds` is non-empty only when release was asked for; accepted work never moves.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorAvailabilityResult
{
    public function __construct(
        public readonly string $workerId,
        public readonly bool $available,
        /** @var list<string> */
        public readonly array $releasedTaskIds,
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
            releasedTaskIds: \array_values(\array_map(strval(...), $data['releasedTaskIds'] ?? [])),
        );
    }
}
