package io.fivexer.sdk.model;

/**
 * The result of ending a break.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerBreakEnded {
    private String workerId;
    private boolean onBreak;
    private String endedAt;

    public String getWorkerId() { return workerId; }
    public boolean isOnBreak() { return onBreak; }
    public String getEndedAt() { return endedAt; }
}
