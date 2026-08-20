package io.fivexer.sdk.model;

import java.util.List;

/**
 * The per-step view of a run — what a canvas UI paints.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowRunSteps {
    private String runId;
    private String status;
    private List<WorkflowRunStep> steps;

    public String getRunId() { return runId; }
    public String getStatus() { return status; }
    public List<WorkflowRunStep> getSteps() { return steps; }
}
