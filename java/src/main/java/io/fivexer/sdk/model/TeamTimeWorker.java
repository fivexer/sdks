package io.fivexer.sdk.model;

import java.util.List;

/**
 * One worker's recorded time and outcomes over the window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamTimeWorker {
    private String workerId;
    private String label;
    private int shiftCount;
    private long onShiftMs;
    private int breakCount;
    private long breakMs;
    private long workingMs;
    private boolean openShift;
    private boolean openBreak;
    private int completed;
    private int offered;
    private int accepted;
    private int rejected;
    private int expired;
    private int failed;
    private int released;
    private List<WorkerTimeEntry> entries;

    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public int getShiftCount() { return shiftCount; }

    /** Time on shift inside the window — breaks included, they happen on shift. */
    public long getOnShiftMs() { return onShiftMs; }

    public int getBreakCount() { return breakCount; }
    public long getBreakMs() { return breakMs; }

    /** {@code onShiftMs - breakMs} — the worked-time figure. */
    public long getWorkingMs() { return workingMs; }

    /** A shift row with no end: on shift now, or never clocked out. */
    public boolean isOpenShift() { return openShift; }

    public boolean isOpenBreak() { return openBreak; }
    public int getCompleted() { return completed; }
    public int getOffered() { return offered; }
    public int getAccepted() { return accepted; }

    /** Offers this worker explicitly turned down. */
    public int getRejected() { return rejected; }

    /** Offers that timed out unanswered while this worker held them. */
    public int getExpired() { return expired; }

    /** Accepted work that ended in failure, including an SLA completion breach. */
    public int getFailed() { return failed; }

    /** Pending work taken back by the idle sweep or an operator. */
    public int getReleased() { return released; }

    /** The raw shift/break log; null unless the query asked for {@code entries}. */
    public List<WorkerTimeEntry> getEntries() { return entries; }
}
