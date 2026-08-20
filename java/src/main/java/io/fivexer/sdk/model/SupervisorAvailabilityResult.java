package io.fivexer.sdk.model;

import java.util.List;

/**
 * Outcome of pausing or resuming a crew member. {@code releasedTaskIds} is non-empty only when release was asked for; accepted work never moves.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorAvailabilityResult {
    private String workerId;
    private Boolean available;
    private List<String> releasedTaskIds;

    public String getWorkerId() { return workerId; }
    public Boolean getAvailable() { return available; }
    public List<String> getReleasedTaskIds() { return releasedTaskIds; }
}
