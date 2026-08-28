<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\TeamPresence;

/**
 * The supervisor view of presence: who is working, on break, or paused. Paused is an operator
 * action; on-break is the worker's own, and the two are reported separately. Requires the
 * control plane.
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
}
