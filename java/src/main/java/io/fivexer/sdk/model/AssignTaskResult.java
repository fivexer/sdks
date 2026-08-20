package io.fivexer.sdk.model;

/**
 * The result of the operator override {@code tasks().assign(...)}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AssignTaskResult {
    private String id;
    private String status;
    private String workerId;
    private String previousWorkerId;  // the worker who held the task before a reassignment; null when it came from the queue

    public String getId() { return id; }
    public String getStatus() { return status; }
    public String getWorkerId() { return workerId; }
    public String getPreviousWorkerId() { return previousWorkerId; }
}
