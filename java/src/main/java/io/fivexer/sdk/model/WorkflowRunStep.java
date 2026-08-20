package io.fivexer.sdk.model;

import java.util.Map;

/**
 * The state of one step within a run.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowRunStep {
    private String stepId;
    private String name;
    private String taskType;
    private String state;
    private String taskId;
    private String workerId;
    private Long completedAt;
    private Map<String, Object> result;

    public String getStepId() { return stepId; }
    public String getName() { return name; }
    public String getTaskType() { return taskType; }
    public String getState() { return state; }
    public String getTaskId() { return taskId; }
    public String getWorkerId() { return workerId; }
    public Long getCompletedAt() { return completedAt; }
    public Map<String, Object> getResult() { return result; }
}
