package io.fivexer.sdk.model;

/**
 * What a workflow step's work is and what a finished result must look like. Shown on the step's
 * task like a task's own context; a success that misses {@code resultContract.schema} is refused
 * with 422 {@code result_invalid} — on the task's completion, or on an external step's callback.
 * A run keeps the data it started with when the definition is later replaced.
 */
public class WorkflowStepData {
    private String title;
    private String description;
    private ResultContract resultContract;

    /** Response construction (Gson). */
    public WorkflowStepData() {}

    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public ResultContract getResultContract() { return resultContract; }

    public WorkflowStepData title(String title) { this.title = title; return this; }
    public WorkflowStepData description(String description) { this.description = description; return this; }
    public WorkflowStepData resultContract(ResultContract resultContract) { this.resultContract = resultContract; return this; }
}
