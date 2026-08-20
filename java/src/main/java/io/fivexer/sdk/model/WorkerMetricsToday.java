package io.fivexer.sdk.model;

/**
 * A worker's own metrics for today.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetricsToday {
    private String workerId;
    private String since;
    private int completedTasks;
    private int breakCount;
    private long totalBreakMs;
    private long longestBreakMs;
    private long workingMs;

    public String getWorkerId() { return workerId; }
    public String getSince() { return since; }
    public int getCompletedTasks() { return completedTasks; }
    public int getBreakCount() { return breakCount; }
    public long getTotalBreakMs() { return totalBreakMs; }
    public long getLongestBreakMs() { return longestBreakMs; }
    public long getWorkingMs() { return workingMs; }
}
