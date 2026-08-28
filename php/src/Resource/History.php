<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\StatsTimeseriesResult;
use Fivexer\SDK\Model\WorkerStatsResult;
use Fivexer\SDK\Model\WorkerTimeseriesResult;

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

    /**
     * Per-worker productivity over the same window: throughput and handle times from the task
     * archive, the offered/accepted/rejected counters, and worked time from the shift log. The
     * row set is the union of those sources, so a worker who was on shift and finished nothing
     * still appears, with zeros.
     *
     * @param string|null $teamId narrow the report to one crew, or null for the whole workspace
     */
    public function workers(
        ?string $from = null,
        ?string $to = null,
        ?string $bucket = null,
        ?string $teamId = null,
    ): WorkerStatsResult {
        $data = $this->client->request('GET', '/stats/workers', null, [
            'from' => $from,
            'to' => $to,
            'bucket' => $bucket,
            'teamId' => $teamId,
        ]) ?? [];
        return WorkerStatsResult::fromArray($data);
    }

    /**
     * One worker's bucketed history — the per-worker twin of timeseries().
     *
     * Worked time and the lifecycle counters are day-grained, so they are populated only on
     * `bucket=day` buckets and are null on hour buckets.
     */
    public function workerTimeseries(
        string $workerId,
        ?string $from = null,
        ?string $to = null,
        ?string $bucket = null,
    ): WorkerTimeseriesResult {
        $data = $this->client->request(
            'GET',
            '/stats/workers/' . \rawurlencode($workerId) . '/timeseries',
            null,
            ['from' => $from, 'to' => $to, 'bucket' => $bucket],
        ) ?? [];
        return WorkerTimeseriesResult::fromArray($data);
    }
}
