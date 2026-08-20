<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The whole board in one response: counts, crew and what needs attention. Deliberately one endpoint rather than four — this is a phone on a depot floor, and four round trips over a bad connection show a board that assembles itself in pieces. `parked` is capped at 50 server-side for the same reason.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorOverview
{
    public function __construct(
        public readonly ?string $teamKey,
        public readonly SupervisorCounts $counts,
        /** @var list<SupervisorCrewMember> — busiest first. */
        public readonly array $crew,
        /** @var list<SupervisorParkedTask> */
        public readonly array $parked,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            teamKey: isset($data['teamKey']) ? (string) $data['teamKey'] : null,
            counts: SupervisorCounts::fromArray($data['counts'] ?? []),
            crew: Json::parseEach($data, 'crew', [SupervisorCrewMember::class, 'fromArray']),
            parked: Json::parseEach($data, 'parked', [SupervisorParkedTask::class, 'fromArray']),
        );
    }
}
