package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * Full detail of one worker, including current load.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerDetail {
    private String id;
    private List<String> tags;
    private Map<String, Double> routingWeights;
    private List<WorkerSkill> skills;  // null when skills are not in use for this worker
    private Integer maxBacklogSize;  // null when no per-worker cap is set; 0 means receive nothing
    private boolean available;
    private int queueDepth;

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Map<String, Double> getRoutingWeights() { return routingWeights; }
    public List<WorkerSkill> getSkills() { return skills; }
    public Integer getMaxBacklogSize() { return maxBacklogSize; }
    public boolean isAvailable() { return available; }
    public int getQueueDepth() { return queueDepth; }
}
