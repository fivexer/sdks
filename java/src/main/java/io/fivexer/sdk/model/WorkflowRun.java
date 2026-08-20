package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * A running (or finished) instance of a workflow definition.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowRun {
    private String id;
    private String workflowId;
    private String status;
    private String currentStepId;
    private String currentTaskId;
    private String initiatorWorkerId;
    private Map<String, Object> context;
    private int definitionVersion;
    private List<WorkflowRunHistoryEntry> history;
    private List<ParallelBranch> parallelBranches;
    private long createdAt;
    private long updatedAt;

    public String getId() { return id; }
    public String getWorkflowId() { return workflowId; }
    public String getStatus() { return status; }
    public String getCurrentStepId() { return currentStepId; }
    public String getCurrentTaskId() { return currentTaskId; }
    public String getInitiatorWorkerId() { return initiatorWorkerId; }
    public Map<String, Object> getContext() { return context; }
    public int getDefinitionVersion() { return definitionVersion; }
    public List<WorkflowRunHistoryEntry> getHistory() { return history; }
    public List<ParallelBranch> getParallelBranches() { return parallelBranches; }
    public long getCreatedAt() { return createdAt; }
    public long getUpdatedAt() { return updatedAt; }
}
