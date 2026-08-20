package io.fivexer.sdk.model;

import java.util.List;

/**
 * Workspace-wide break rollup.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkspaceBreakMetrics {
    private List<WorkerBreakMetric> workers;
    private long totalBreakMs;
    private int breakCount;
    private int activeCount;

    public List<WorkerBreakMetric> getWorkers() { return workers; }
    public long getTotalBreakMs() { return totalBreakMs; }
    public int getBreakCount() { return breakCount; }
    public int getActiveCount() { return activeCount; }
}
