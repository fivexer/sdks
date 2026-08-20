package io.fivexer.sdk.model;

/**
 * One worker's presence.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamPresenceMember {
    private String workerId;
    private String label;
    private String status;  // working | on-break | paused
    private String breakStartedAt;
    private String breakReason;

    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public String getStatus() { return status; }
    public String getBreakStartedAt() { return breakStartedAt; }
    public String getBreakReason() { return breakReason; }
}
