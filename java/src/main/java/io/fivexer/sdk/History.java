package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.StatsTimeseriesResult;
import io.fivexer.sdk.model.WorkerStatsResult;
import io.fivexer.sdk.model.WorkerTimeseriesResult;

/**
 * Historical stats from the archive. Requires the control plane — a data-plane-only deployment
 * answers {@code 501 history_unavailable}, which is a deployment fact rather than a failure.
 */
public final class History {

    private final Fivexer client;

    History(Fivexer client) {
        this.client = client;
    }

    /** Throughput and latency over a window; defaults to the last 24h bucketed hourly. */
    public StatsTimeseriesResult timeseries(StatsWindowQuery query) {
        StatsWindowQuery q = query == null ? new StatsWindowQuery() : query;
        return client.request("GET", "/stats/timeseries", null, q.toQuery(), StatsTimeseriesResult.class, false);
    }

    public StatsTimeseriesResult timeseries() {
        return timeseries(null);
    }

    /**
     * Per-worker productivity over the same window: throughput and handle times from the task
     * archive, the offered/accepted/rejected counters, and worked time from the shift log. The row
     * set is the union of those sources, so a worker who was on shift and finished nothing still
     * appears, with zeros.
     */
    public WorkerStatsResult workers(WorkerStatsQuery query) {
        WorkerStatsQuery q = query == null ? new WorkerStatsQuery() : query;
        return client.request("GET", "/stats/workers", null, q.toQuery(), WorkerStatsResult.class, false);
    }

    /** The same report for a plain window, with no crew filter. */
    public WorkerStatsResult workers(StatsWindowQuery query) {
        StatsWindowQuery q = query == null ? new StatsWindowQuery() : query;
        return client.request("GET", "/stats/workers", null, q.toQuery(), WorkerStatsResult.class, false);
    }

    public WorkerStatsResult workers() {
        return workers(new WorkerStatsQuery());
    }

    /**
     * One worker's bucketed history — the per-worker twin of {@link #timeseries}.
     *
     * <p>Worked time and the lifecycle counters are day-grained, so they are populated only on
     * {@code bucket=day} buckets and are null on hour buckets.
     */
    public WorkerTimeseriesResult workerTimeseries(String workerId, StatsWindowQuery query) {
        StatsWindowQuery q = query == null ? new StatsWindowQuery() : query;
        return client.request("GET", "/stats/workers/" + Json.enc(workerId) + "/timeseries", null,
                q.toQuery(), WorkerTimeseriesResult.class, false);
    }

    public WorkerTimeseriesResult workerTimeseries(String workerId) {
        return workerTimeseries(workerId, null);
    }
}
