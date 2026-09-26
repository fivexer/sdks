<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\TeamPresence;
use Fivexer\SDK\Model\TeamTimeQuery;
use Fivexer\SDK\Model\TeamTimeResult;

/**
 * The supervisor view of the team: presence — who is working, on break, or paused — and the
 * working-time record. Paused is an operator action; on-break is the worker's own, and the two
 * are reported separately. Requires the control plane.
 */
final class Team
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** @param string|null $teamId narrow the view to one crew, or null for the whole workspace */
    public function presence(?string $teamId = null): TeamPresence
    {
        return TeamPresence::fromArray(
            $this->client->request('GET', '/team/presence', null, ['teamId' => $teamId]) ?? []
        );
    }

    /**
     * Worked time, breaks and outcomes per worker and per day for a window — the working-time
     * report. Days are UTC; the per-day series and each worker's raw log come back only when the
     * query asks for entries.
     */
    public function time(?TeamTimeQuery $query = null): TeamTimeResult
    {
        return TeamTimeResult::fromArray(
            $this->client->request('GET', '/team/time', null, ($query ?? new TeamTimeQuery())->toQuery()) ?? []
        );
    }
}
