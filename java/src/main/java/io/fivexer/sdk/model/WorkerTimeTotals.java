package io.fivexer.sdk.model;

/**
 * The totals of a worker's time log for a window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTimeTotals {
    private int shiftCount;
    private long onShiftMs;
    private int breakCount;
    private long breakMs;
    private long workingMs;

    public int getShiftCount() { return shiftCount; }

    /** Time on shift, breaks included. */
    public long getOnShiftMs() { return onShiftMs; }

    public int getBreakCount() { return breakCount; }
    public long getBreakMs() { return breakMs; }

    /** {@code onShiftMs - breakMs}: breaks are time inside a shift, not beside it. */
    public long getWorkingMs() { return workingMs; }
}
