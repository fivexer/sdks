package io.fivexer.sdk.model;

import java.util.List;

/** Result of {@code GET /v1/workers/{id}/queue} — a worker's current task ids. */
public class WorkerQueue {
    private String workerId;
    private List<String> taskIds;

    public String getWorkerId() { return workerId; }
    public List<String> getTaskIds() { return taskIds; }
}
