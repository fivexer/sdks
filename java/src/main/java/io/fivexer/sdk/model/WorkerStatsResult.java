package io.fivexer.sdk.model;

import java.util.List;

/**
 * Per-worker throughput over a window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerStatsResult {
    private String from;
    private String to;
    private List<WorkerProductivity> workers;

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public List<WorkerProductivity> getWorkers() { return workers; }
}
