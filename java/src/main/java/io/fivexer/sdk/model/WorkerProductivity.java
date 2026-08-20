package io.fivexer.sdk.model;

/**
 * One worker's throughput over a window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerProductivity {
    private String workerId;
    private int completed;
    private int cancelled;
    private Double avgWaitMs;
    private Double avgHandleMs;

    public String getWorkerId() { return workerId; }
    public int getCompleted() { return completed; }
    public int getCancelled() { return cancelled; }
    public Double getAvgWaitMs() { return avgWaitMs; }
    public Double getAvgHandleMs() { return avgHandleMs; }
}
