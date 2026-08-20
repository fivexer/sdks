<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Who is working vs on break vs paused.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamPresence
{
    public function __construct(
        /** @var list<TeamPresenceMember> */
        public readonly array $workers,
        public readonly TeamPresenceCounts $counts,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workers: Json::parseEach($data, 'workers', [TeamPresenceMember::class, 'fromArray']),
            counts: TeamPresenceCounts::fromArray($data['counts'] ?? []),
        );
    }
}
