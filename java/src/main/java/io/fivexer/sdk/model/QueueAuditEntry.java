package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * One queued task and what is stopping it being matched.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class QueueAuditEntry {
    private String taskId;
    private List<String> tags;
    private Long waitingMs;
    private Integer eligibleWorkerCount;
    private List<String> uncoveredTags;
    private Map<String, Integer> blockers;

    public String getTaskId() { return taskId; }
    public List<String> getTags() { return tags; }
    public Long getWaitingMs() { return waitingMs; }
    public Integer getEligibleWorkerCount() { return eligibleWorkerCount; }
    public List<String> getUncoveredTags() { return uncoveredTags; }
    public Map<String, Integer> getBlockers() { return blockers; }
}
