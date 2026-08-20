package io.fivexer.sdk.model;

import java.util.Map;

/**
 * One branch of a parallel step.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class ParallelBranch {
    private String stepId;
    private String assignmentId;
    private String status;
    private Map<String, Object> result;

    public String getStepId() { return stepId; }
    public String getAssignmentId() { return assignmentId; }
    public String getStatus() { return status; }
    public Map<String, Object> getResult() { return result; }
}
