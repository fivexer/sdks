package io.fivexer.sdk.model;

/**
 * Outcome of handing a task to a crew member.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorAssignResult {
    private String id;
    private String workerId;
    private String status;

    public String getId() { return id; }
    public String getWorkerId() { return workerId; }
    public String getStatus() { return status; }
}
