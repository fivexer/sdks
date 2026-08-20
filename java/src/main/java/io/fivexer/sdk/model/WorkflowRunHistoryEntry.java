package io.fivexer.sdk.model;

import java.util.Map;

/**
 * One completed step in a run's history.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowRunHistoryEntry {
    private String stepId;
    private String taskId;
    private String workerId;
    private long completedAt;
    private Map<String, Object> result;

    public String getStepId() { return stepId; }
    public String getTaskId() { return taskId; }
    public String getWorkerId() { return workerId; }
    public long getCompletedAt() { return completedAt; }
    public Map<String, Object> getResult() { return result; }
}
