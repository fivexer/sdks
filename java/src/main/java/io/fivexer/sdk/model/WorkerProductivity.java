package io.fivexer.sdk.model;

/**
 * One worker's window report: what they finished, how they responded, and how long they worked.
 *
 * <p>Three sources merged server-side — the task archive (throughput and handle times), the
 * synced day counters (offers, accepts, rejections) and the shift log ({@code onShiftMs} less
 * overlapping breaks is {@code workingMs}). Measured facts, never a score.
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
    private Double p50HandleMs;
    private Double p95HandleMs;
    private int offered;
    private int accepted;
    private int rejected;
    private int failed;
    private int expired;
    private int released;
    private Double acceptanceRate;
    private long onShiftMs;
    private long breakMs;
    private long workingMs;
    private int shiftCount;
    private Double utilization;

    public String getWorkerId() { return workerId; }
    public int getCompleted() { return completed; }
    public int getCancelled() { return cancelled; }
    public Double getAvgWaitMs() { return avgWaitMs; }
    public Double getAvgHandleMs() { return avgHandleMs; }
    public Double getP50HandleMs() { return p50HandleMs; }
    public Double getP95HandleMs() { return p95HandleMs; }
    public int getOffered() { return offered; }
    public int getAccepted() { return accepted; }
    public int getRejected() { return rejected; }
    public int getFailed() { return failed; }
    public int getExpired() { return expired; }
    public int getReleased() { return released; }

    /** accepted / offered over the window; null before any offer, never zero. */
    public Double getAcceptanceRate() { return acceptanceRate; }

    /** Time on shift inside the window, breaks included. */
    public long getOnShiftMs() { return onShiftMs; }

    public long getBreakMs() { return breakMs; }

    /** {@code onShiftMs - breakMs}, floored at zero — worked time. */
    public long getWorkingMs() { return workingMs; }

    public int getShiftCount() { return shiftCount; }

    /**
     * Share of worked time spent inside accepted tasks (handle time ÷ working time). Can exceed
     * 1 for a worker handling overlapping tasks, and is null when nothing was measured.
     */
    public Double getUtilization() { return utilization; }
}
