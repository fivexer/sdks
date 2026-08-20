package io.fivexer.sdk.model;

/**
 * Result of advancing the escalation ladder. {@code escalated: false, parked: true} is the end of the ladder — the task has left matching, and this flag is the only thing that says so.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskEscalation {
    private String id;
    private Boolean escalated;
    private Boolean parked;
    private Integer escalationLevel;

    public String getId() { return id; }
    public Boolean getEscalated() { return escalated; }
    public Boolean getParked() { return parked; }
    public Integer getEscalationLevel() { return escalationLevel; }
}
