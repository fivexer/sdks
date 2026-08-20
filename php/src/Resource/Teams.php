<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\PatchTeam;
use Fivexer\SDK\Model\Team;
use Fivexer\SDK\Model\TeamMembers;
use Fivexer\SDK\Model\TeamRoster;
use Fivexer\SDK\Model\UpsertTeam;

/**
 * Teams.
 *
 * A team is a routing primitive, not a label: the server derives a `tag` from the key and
 * matching sees that tag, so adding a worker to a team changes what work reaches them.
 */
final class Teams
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function create(UpsertTeam $input): Team
    {
        return Team::fromArray($this->client->request('POST', '/teams', $input->toArray()) ?? []);
    }

    /** @return list<Team> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/teams') ?? [];
        /** @var list<Team> */
        return Json::parseEach($data, 'teams', [Team::class, 'fromArray']);
    }

    public function get(string $teamId): Team
    {
        return Team::fromArray($this->client->request('GET', '/teams/' . \rawurlencode($teamId)) ?? []);
    }

    /** Partial update — the key is immutable. */
    public function patch(string $teamId, PatchTeam $patch): Team
    {
        return Team::fromArray(
            $this->client->request('PATCH', '/teams/' . \rawurlencode($teamId), $patch->toArray()) ?? []
        );
    }

    public function remove(string $teamId): void
    {
        $this->client->request('DELETE', '/teams/' . \rawurlencode($teamId));
    }

    public function members(string $teamId): TeamMembers
    {
        return TeamMembers::fromArray(
            $this->client->request('GET', '/teams/' . \rawurlencode($teamId) . '/members') ?? []
        );
    }

    /**
     * Replaces the roster wholesale — a worker absent from $workerIds is removed from the team.
     *
     * @param list<string> $workerIds
     */
    public function setMembers(string $teamId, array $workerIds): TeamRoster
    {
        return TeamRoster::fromArray(
            $this->client->request(
                'PUT',
                '/teams/' . \rawurlencode($teamId) . '/members',
                ['workerIds' => $workerIds]
            ) ?? []
        );
    }
}
