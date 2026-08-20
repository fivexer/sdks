package io.fivexer.sdk.model;

import java.util.List;

/**
 * Who is working vs on break vs paused.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamPresence {
    private List<TeamPresenceMember> workers;
    private TeamPresenceCounts counts;

    public List<TeamPresenceMember> getWorkers() { return workers; }
    public TeamPresenceCounts getCounts() { return counts; }
}
