package io.fivexer.sdk.model;

import java.util.List;

/**
 * A dry run: what would happen to this task. Creates and reserves nothing.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskCheckReport {
    private List<TaskCheckIssue> issues;
    private Integer eligibleWorkerCount;
    private List<String> uncoveredTags;
    private Long evaluatedAt;

    public List<TaskCheckIssue> getIssues() { return issues; }
    public Integer getEligibleWorkerCount() { return eligibleWorkerCount; }
    public List<String> getUncoveredTags() { return uncoveredTags; }
    public Long getEvaluatedAt() { return evaluatedAt; }
}
