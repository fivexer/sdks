package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * One node of a workflow graph, used both when saving a definition and when reading one back.
 *
 * <p>{@code defaultNextStepId} is genuinely tri-state, which is why it gets special handling
 * rather than riding on Gson's omit-nulls rule: <em>absent</em> means no fallback was declared,
 * while an explicit {@code null} ends the workflow after this step. Call {@link #endWorkflow()}
 * to express the latter — a plain null field cannot be told apart from "never set".
 */
public class WorkflowStep {
    private String id;
    private String name;
    private String taskType;   // assignment | machine | external
    private Map<String, Object> assignmentTemplate;
    private Object targetUser; // "initiator" | "previous" | a worker id | {"tag": "..."}
    private Map<String, Object> machineTask;
    private Map<String, Object> external;
    private List<WorkflowRouting> routing;
    private String defaultNextStepId;
    private List<String> parallelStepIds;
    private Boolean waitForAll;
    private String failurePolicy;  // abort | continue | retry
    private Integer maxRetries;
    private Long timeoutMs;

    /**
     * True when the wire carried an explicit {@code defaultNextStepId: null}. Gson leaves this
     * false for a step that simply omitted the key, preserving the distinction on a round trip.
     */
    private transient boolean terminal;

    /** Response construction (Gson). */
    public WorkflowStep() {}

    public WorkflowStep(String id, String name) {
        this.id = id;
        this.name = name;
    }

    public String getId() { return id; }
    public String getName() { return name; }
    public String getTaskType() { return taskType; }
    public Map<String, Object> getAssignmentTemplate() { return assignmentTemplate; }
    public Object getTargetUser() { return targetUser; }
    public Map<String, Object> getMachineTask() { return machineTask; }
    public Map<String, Object> getExternal() { return external; }
    public List<WorkflowRouting> getRouting() { return routing; }
    public String getDefaultNextStepId() { return defaultNextStepId; }
    public List<String> getParallelStepIds() { return parallelStepIds; }
    public Boolean getWaitForAll() { return waitForAll; }
    public String getFailurePolicy() { return failurePolicy; }
    public Integer getMaxRetries() { return maxRetries; }
    public Long getTimeoutMs() { return timeoutMs; }

    /** True when this step ends the workflow (an explicit null successor). */
    public boolean isTerminal() { return terminal; }

    public WorkflowStep taskType(String taskType) { this.taskType = taskType; return this; }
    public WorkflowStep assignmentTemplate(Map<String, Object> t) { this.assignmentTemplate = t; return this; }
    public WorkflowStep targetUser(Object targetUser) { this.targetUser = targetUser; return this; }
    public WorkflowStep machineTask(Map<String, Object> t) { this.machineTask = t; return this; }
    public WorkflowStep external(Map<String, Object> external) { this.external = external; return this; }
    public WorkflowStep routing(List<WorkflowRouting> routing) { this.routing = routing; return this; }
    public WorkflowStep defaultNextStepId(String id) { this.defaultNextStepId = id; this.terminal = false; return this; }
    public WorkflowStep parallelStepIds(List<String> ids) { this.parallelStepIds = ids; return this; }
    public WorkflowStep waitForAll(boolean waitForAll) { this.waitForAll = waitForAll; return this; }
    public WorkflowStep failurePolicy(String policy) { this.failurePolicy = policy; return this; }
    public WorkflowStep maxRetries(int maxRetries) { this.maxRetries = maxRetries; return this; }
    public WorkflowStep timeoutMs(long timeoutMs) { this.timeoutMs = timeoutMs; return this; }

    /** Mark this step as ending the workflow, serialising {@code defaultNextStepId: null}. */
    public WorkflowStep endWorkflow() {
        this.defaultNextStepId = null;
        this.terminal = true;
        return this;
    }

    /**
     * Serialise, omitting unset fields but writing the explicit null successor that terminates
     * a workflow.
     */
    public com.google.gson.JsonObject toJsonTree() {
        com.google.gson.JsonObject o = Json.tree(this).getAsJsonObject();
        if (terminal) {
            o.add("defaultNextStepId", com.google.gson.JsonNull.INSTANCE);
        }
        return o;
    }
}
