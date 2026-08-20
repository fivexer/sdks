package io.fivexer.sdk.model;

/**
 * A worker's own shift switch, after flipping it.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerAvailabilityState {
    private String workerId;
    private Boolean available;

    public String getWorkerId() { return workerId; }
    public Boolean getAvailable() { return available; }
}
