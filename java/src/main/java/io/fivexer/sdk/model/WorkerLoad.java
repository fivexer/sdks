package io.fivexer.sdk.model;

/**
 * One worker's backlog against their cap.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLoad {
    private String workerId;
    private int backlog;
    private int maxBacklogSize;
    private boolean available;

    public String getWorkerId() { return workerId; }
    public int getBacklog() { return backlog; }
    public int getMaxBacklogSize() { return maxBacklogSize; }
    public boolean isAvailable() { return available; }
}
