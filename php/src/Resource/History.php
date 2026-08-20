<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\StatsTimeseriesResult;
use Fivexer\SDK\Model\WorkerStatsResult;

/**
 * Historical stats from the archive. Requires the control plane — a data-plane-only deployment
 * answers 501 history_unavailable, which is a deployment fact rather than a failure.
 */
final class History
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * Throughput and latency over a window; defaults to the last 24h bucketed hourly.
     *
     * @param string|null $from ISO-8601
     * @param string|null $to ISO-8601
     * @param string|null $bucket 'hour'|'day'
     */
    public function timeseries(?string $from = null, ?string $to = null, ?string $bucket = null): StatsTimeseriesResult
    {
        $data = $this->client->request('GET', '/stats/timeseries', null, [
            'from' => $from,
            'to' => $to,
            'bucket' => $bucket,
        ]) ?? [];
        return StatsTimeseriesResult::fromArray($data);
    }

    /** Per-worker productivity over the same window. */
    public function workers(?string $from = null, ?string $to = null, ?string $bucket = null): WorkerStatsResult
    {
        $data = $this->client->request('GET', '/stats/workers', null, [
            'from' => $from,
            'to' => $to,
            'bucket' => $bucket,
        ]) ?? [];
        return WorkerStatsResult::fromArray($data);
    }
}
