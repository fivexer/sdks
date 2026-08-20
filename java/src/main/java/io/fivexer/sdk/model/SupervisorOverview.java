package io.fivexer.sdk.model;

import java.util.List;

/**
 * The whole board in one response: counts, crew and what needs attention. Deliberately one endpoint rather than four — this is a phone on a depot floor, and four round trips over a bad connection show a board that assembles itself in pieces. {@code parked} is capped at 50 server-side for the same reason.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorOverview {
    private String teamKey;
    private SupervisorCounts counts;
    private List<SupervisorCrewMember> crew;
    private List<SupervisorParkedTask> parked;

    public String getTeamKey() { return teamKey; }
    public SupervisorCounts getCounts() { return counts; }
    public List<SupervisorCrewMember> getCrew() { return crew; }
    public List<SupervisorParkedTask> getParked() { return parked; }
}
