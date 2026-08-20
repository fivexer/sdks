package io.fivexer.sdk.model;

/**
 * One break entry, open or closed.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerBreak {
    private String id;
    private String startedAt;
    private String endedAt;  // null while the break is open
    private String reason;
    private long durationMs;

    public String getId() { return id; }
    public String getStartedAt() { return startedAt; }
    public String getEndedAt() { return endedAt; }
    public String getReason() { return reason; }
    public long getDurationMs() { return durationMs; }
}
