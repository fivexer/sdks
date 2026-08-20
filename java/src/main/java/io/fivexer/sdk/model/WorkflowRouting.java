package io.fivexer.sdk.model;

/**
 * A branch rule. {@code condition} is evaluated against the step result and the first match
 * wins. Used both when saving a definition and when reading one back.
 */
public class WorkflowRouting {
    private String condition;
    private String targetStepId;

    /** Response construction (Gson). */
    public WorkflowRouting() {}

    public WorkflowRouting(String condition, String targetStepId) {
        this.condition = condition;
        this.targetStepId = targetStepId;
    }

    public String getCondition() { return condition; }
    public String getTargetStepId() { return targetStepId; }
}
