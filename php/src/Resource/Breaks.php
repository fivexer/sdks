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
     * @param string|null $from ISO-8601 start, or null
     * @param string|null $to ISO-8601 end, or null (the window then defaults to today)
     */
    public function metrics(?string $from = null, ?string $to = null): WorkspaceBreakMetrics
    {
        $data = $this->client->request('GET', '/breaks/metrics', null, ['from' => $from, 'to' => $to]) ?? [];
        return WorkspaceBreakMetrics::fromArray($data);
    }
}
