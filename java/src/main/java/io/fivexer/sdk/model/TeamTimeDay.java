package io.fivexer.sdk.model;

/**
 * One UTC day of the team's recorded time.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamTimeDay {
    private String day;
    private long onShiftMs;
    private long breakMs;
    private long workingMs;

    /** {@code YYYY-MM-DD}, UTC. */
    public String getDay() { return day; }

    public long getOnShiftMs() { return onShiftMs; }
    public long getBreakMs() { return breakMs; }
    public long getWorkingMs() { return workingMs; }
}
