package io.fivexer.sdk.model;

import java.util.List;

/**
 * One worker's rolling window: recorded time, throughput and response counters.
 *
 * <p>Medians are null for a worker with no history, and {@code acceptanceRate} is null before any
 * offer — a rate over nothing is not zero.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetricsPeriod {
    private String from;
    private String to;
    private List<WorkerMetricsWindowDay> days;
    private Long medianWaitMs;
    private Long medianCycleMs;
    private int shiftCount;
    private long onShiftMs;
    private long breakMs;
    private long workingMs;
    private int offered;
    private int accepted;
    private int rejected;
    private int completed;
    private int failed;
    private int expired;
    private int released;
    private Double acceptanceRate;

    public String getFrom() { return from; }
    public String getTo() { return to; }

    /** The per-day completion counts, or null when the window is not broken down. */
    public List<WorkerMetricsWindowDay> getDays() { return days; }

    public Long getMedianWaitMs() { return medianWaitMs; }
    public Long getMedianCycleMs() { return medianCycleMs; }
    public int getShiftCount() { return shiftCount; }

    /** Time on shift in the window, breaks included. */
    public long getOnShiftMs() { return onShiftMs; }

    public long getBreakMs() { return breakMs; }

    /** {@code onShiftMs - breakMs} — worked time. */
    public long getWorkingMs() { return workingMs; }

    public int getOffered() { return offered; }
    public int getAccepted() { return accepted; }
    public int getRejected() { return rejected; }
    public int getCompleted() { return completed; }
    public int getFailed() { return failed; }
    public int getExpired() { return expired; }
    public int getReleased() { return released; }

    /** accepted / offered over the window; null before any offer, never zero. */
    public Double getAcceptanceRate() { return acceptanceRate; }
}
