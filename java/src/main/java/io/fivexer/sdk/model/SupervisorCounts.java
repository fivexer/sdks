package io.fivexer.sdk.model;

/**
 * The board's headline numbers.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorCounts {
    private Integer queued;
    private Integer pending;
    private Integer parked;
    private Long oldestWaitMs;

    public Integer getQueued() { return queued; }
    public Integer getPending() { return pending; }
    public Integer getParked() { return parked; }
    public Long getOldestWaitMs() { return oldestWaitMs; }
}
