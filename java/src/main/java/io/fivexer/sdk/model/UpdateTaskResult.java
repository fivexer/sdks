package io.fivexer.sdk.model;

import java.util.List;

/**
 * The result of editing a live task.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class UpdateTaskResult {
    private String id;
    private String status;
    private List<String> tags;
    private Double priority;
    private boolean requeued;
    private String previousWorkerId;

    public String getId() { return id; }

    /**
     * Where the task is now — {@code queued}, {@code pending}, {@code accepted}, {@code parked}
     * or {@code scheduled}. {@code queued} when the edit sent it back for rematching.
     */
    public String getStatus() { return status; }

    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }

    /**
     * Whether the new routing tags no longer reach the worker who held this task, so it was taken
     * back and returned to the queue. Without reading this, a retag that moved work looks like a
     * silent no-op.
     */
    public boolean isRequeued() { return requeued; }

    /** The worker who lost the task, when {@link #isRequeued()} is true; otherwise null. */
    public String getPreviousWorkerId() { return previousWorkerId; }
}
