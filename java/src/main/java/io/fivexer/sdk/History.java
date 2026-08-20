package io.fivexer.sdk;

import io.fivexer.sdk.model.StatsTimeseriesResult;
import io.fivexer.sdk.model.WorkerStatsResult;

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

    /** Per-worker productivity over the same window. */
    public WorkerStatsResult workers(StatsWindowQuery query) {
        StatsWindowQuery q = query == null ? new StatsWindowQuery() : query;
        return client.request("GET", "/stats/workers", null, q.toQuery(), WorkerStatsResult.class, false);
    }

    public WorkerStatsResult workers() {
        return workers(null);
    }
}
