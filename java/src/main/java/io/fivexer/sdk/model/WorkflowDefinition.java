package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * A stored workflow graph.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowDefinition {
    private String id;
    private String name;
    private int version;
    private String initialStepId;
    private List<WorkflowStep> steps;
    private Long defaultTimeoutMs;
    private Map<String, Object> metadata;

    public String getId() { return id; }
    public String getName() { return name; }
    public int getVersion() { return version; }
    public String getInitialStepId() { return initialStepId; }
    public List<WorkflowStep> getSteps() { return steps; }
    public Long getDefaultTimeoutMs() { return defaultTimeoutMs; }
    public Map<String, Object> getMetadata() { return metadata; }
}
