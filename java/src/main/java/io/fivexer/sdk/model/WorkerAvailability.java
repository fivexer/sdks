package io.fivexer.sdk.model;

import java.util.List;

/**
 * The result of pausing or resuming a worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerAvailability {
    private String id;
    private boolean available;
    private List<String> releasedTaskIds;  // only present when the pause requested a backlog release

    public String getId() { return id; }
    public boolean isAvailable() { return available; }
    public List<String> getReleasedTaskIds() { return releasedTaskIds; }
}
