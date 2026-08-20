package io.fivexer.sdk.model;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * The body accepted by {@code workflows().save(...)}. The workflow id comes from the URL and is
 * deliberately never sent in the body.
 */
public class WorkflowDefinitionInput {
    private String name;
    private List<WorkflowStep> steps;
    private Integer version;
    private String initialStepId;
    private Long defaultTimeoutMs;
    private Map<String, Object> metadata;

    public WorkflowDefinitionInput(String name, List<WorkflowStep> steps) {
        if (name == null) {
            throw new IllegalArgumentException("name is required");
        }
        if (steps == null) {
            throw new IllegalArgumentException("steps is required");
        }
        this.name = name;
        this.steps = steps;
    }

    public String getName() { return name; }
    public List<WorkflowStep> getSteps() { return steps; }
    public Integer getVersion() { return version; }
    public String getInitialStepId() { return initialStepId; }
    public Long getDefaultTimeoutMs() { return defaultTimeoutMs; }
    public Map<String, Object> getMetadata() { return metadata; }

    public WorkflowDefinitionInput version(int version) { this.version = version; return this; }
    public WorkflowDefinitionInput initialStepId(String id) { this.initialStepId = id; return this; }
    public WorkflowDefinitionInput defaultTimeoutMs(long ms) { this.defaultTimeoutMs = ms; return this; }
    public WorkflowDefinitionInput metadata(Map<String, Object> metadata) { this.metadata = metadata; return this; }

    /**
     * Serialise for the wire. Steps go through {@link WorkflowStep#toJsonTree()} rather than
     * plain serialisation so a terminal step keeps its explicit null successor.
     */
    public String toJson() {
        JsonObject o = new JsonObject();
        o.addProperty("name", name);
        JsonArray array = new JsonArray();
        for (WorkflowStep step : steps) {
            array.add(step.toJsonTree());
        }
        o.add("steps", array);
        if (version != null) o.addProperty("version", version);
        if (initialStepId != null) o.addProperty("initialStepId", initialStepId);
        if (defaultTimeoutMs != null) o.addProperty("defaultTimeoutMs", defaultTimeoutMs);
        if (metadata != null) o.add("metadata", Json.tree(metadata));
        return o.toString();
    }
}
