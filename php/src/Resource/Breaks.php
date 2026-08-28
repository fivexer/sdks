<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\WorkspaceBreakMetrics;

/** Per-worker break rollups for a window. Requires the control plane. */
final class Breaks
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * With an explicit `$to`, durations are clipped to the window; without one, a break still
     * running is measured to now.
     *
     * @param string|null $from ISO-8601 start, or null
     * @param string|null $to ISO-8601 end, or null (the window then defaults to today)
     * @param string|null $teamId narrow the rollup to one crew, or null for the whole workspace
     * @param string|null $workerId narrow it to one worker, or null for everyone
     */
    public function metrics(
        ?string $from = null,
        ?string $to = null,
        ?string $teamId = null,
        ?string $workerId = null,
    ): WorkspaceBreakMetrics {
        $data = $this->client->request('GET', '/breaks/metrics', null, [
            'from' => $from,
            'to' => $to,
            'teamId' => $teamId,
            'workerId' => $workerId,
        ]) ?? [];
        return WorkspaceBreakMetrics::fromArray($data);
    }
}
