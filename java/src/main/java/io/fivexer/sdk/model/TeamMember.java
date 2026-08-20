package io.fivexer.sdk.model;

/**
 * One worker's membership of a team.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamMember {
    private String workerId;
    private String role;
    private String addedAt;

    public String getWorkerId() { return workerId; }
    public String getRole() { return role; }
    public String getAddedAt() { return addedAt; }
}
