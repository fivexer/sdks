package io.fivexer.sdk.model;

import java.util.List;

/**
 * A worker's own recent throughput. Medians are null for a worker with no history, not zero.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetricsWindow {
    private String workerId;
    private String since;
    private List<WorkerMetricsWindowDay> days;
    private Long medianWaitMs;
    private Long medianCycleMs;

    public String getWorkerId() { return workerId; }
    public String getSince() { return since; }
    public List<WorkerMetricsWindowDay> getDays() { return days; }
    public Long getMedianWaitMs() { return medianWaitMs; }
    public Long getMedianCycleMs() { return medianCycleMs; }
}
