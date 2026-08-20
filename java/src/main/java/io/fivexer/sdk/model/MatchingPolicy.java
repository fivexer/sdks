package io.fivexer.sdk.model;

/**
 * The live balancer policy applied to bulk matching passes.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class MatchingPolicy {
    private String fairness;  // first-come | best-match | balanced | spread-work
    private Integer maxTasksPerWindow;
    private Long windowMs;

    public String getFairness() { return fairness; }
    public Integer getMaxTasksPerWindow() { return maxTasksPerWindow; }
    public Long getWindowMs() { return windowMs; }
}
