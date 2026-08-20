<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A team's roster.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamMembers
{
    public function __construct(
        public readonly string $teamId,
        /** @var list<TeamMember> */
        public readonly array $members,
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
            members: Json::parseEach($data, 'members', [TeamMember::class, 'fromArray']),
        );
    }
}
