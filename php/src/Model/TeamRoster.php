<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Result of replacing a team's membership wholesale.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamRoster
{
    public function __construct(
        public readonly string $teamId,
        /** @var list<string> */
        public readonly array $workerIds,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            teamId: (string) ($data['teamId'] ?? ''),
            workerIds: \array_values(\array_map(strval(...), $data['workerIds'] ?? [])),
        );
    }
}
