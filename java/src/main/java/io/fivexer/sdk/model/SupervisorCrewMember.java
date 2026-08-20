package io.fivexer.sdk.model;

/**
 * One crew member's current load.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorCrewMember {
    private String workerId;
    private Integer backlog;
    private Boolean available;

    public String getWorkerId() { return workerId; }
    public Integer getBacklog() { return backlog; }
    public Boolean getAvailable() { return available; }
}
