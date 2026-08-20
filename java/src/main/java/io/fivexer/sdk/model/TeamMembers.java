package io.fivexer.sdk.model;

import java.util.List;

/**
 * A team's roster, as returned by the members endpoint.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamMembers {
    private String teamId;
    private List<TeamMember> members;

    public String getTeamId() { return teamId; }
    public List<TeamMember> getMembers() { return members; }
}
