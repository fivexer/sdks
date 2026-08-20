package io.fivexer.sdk.model;

/**
 * Presence totals by state.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamPresenceCounts {
    private int working;
    private int onBreak;
    private int paused;
    private int total;

    public int getWorking() { return working; }
    public int getOnBreak() { return onBreak; }
    public int getPaused() { return paused; }
    public int getTotal() { return total; }
}
