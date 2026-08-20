package io.fivexer.sdk.model;

import java.util.List;

/**
 * Result of replacing a team's membership wholesale.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamRoster {
    private String teamId;
    private List<String> workerIds;

    public String getTeamId() { return teamId; }
    public List<String> getWorkerIds() { return workerIds; }
}
