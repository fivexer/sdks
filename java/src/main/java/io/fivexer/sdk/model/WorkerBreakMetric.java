package io.fivexer.sdk.model;

/**
 * One worker's break totals over a window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerBreakMetric {
    private String workerId;
    private String label;
    private int count;
    private long totalBreakMs;
    private long longestBreakMs;
    private boolean active;

    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public int getCount() { return count; }
    public long getTotalBreakMs() { return totalBreakMs; }
    public long getLongestBreakMs() { return longestBreakMs; }
    public boolean isActive() { return active; }
}
