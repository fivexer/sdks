package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for the team listing endpoint.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamList {
    private List<Team> teams;

    public List<Team> getTeams() { return teams; }
}
