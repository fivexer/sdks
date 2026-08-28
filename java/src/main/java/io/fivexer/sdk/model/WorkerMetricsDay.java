package io.fivexer.sdk.model;

/**
 * Today's recorded time and throughput for one worker, as an operator reads it.
 *
 * <p>A shift is a recorded stretch of availability; breaks are time inside one, so
 * {@code workingMs} is {@code onShiftMs} minus break time. Measured facts, never a score.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetricsDay {
    private String since;
    private int completedTasks;
    private int shiftCount;
    private long onShiftMs;
    private int breakCount;
    private long totalBreakMs;
    private long longestBreakMs;
    private long workingMs;

    public String getSince() { return since; }
    public int getCompletedTasks() { return completedTasks; }
    public int getShiftCount() { return shiftCount; }

    /** Time on shift today, breaks included. */
    public long getOnShiftMs() { return onShiftMs; }

    public int getBreakCount() { return breakCount; }
    public long getTotalBreakMs() { return totalBreakMs; }
    public long getLongestBreakMs() { return longestBreakMs; }

    /** {@code onShiftMs - totalBreakMs} — worked time. */
    public long getWorkingMs() { return workingMs; }
}
