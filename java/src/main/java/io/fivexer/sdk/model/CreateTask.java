package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * Input for {@code POST /v1/tasks}. {@code tags} is required; the rest are optional.
 *
 * <p>Carries the rich-data fields too ({@code title}/{@code description}/{@code context}/
 * {@code references}), so a task can be created with its full context in one call instead of a
 * create plus a separate context write.
 *
 * <pre>{@code
 * Task task = client.tasks().create(new CreateTask(List.of("english"))
 *         .priority(90)
 *         .title("Refund request"));
 * }</pre>
 */
public class CreateTask {
    private List<String> tags;
    private String id;
    private Double priority;
    private Map<String, Double> skillThresholds;
    private List<String> vetoedWorkers;
    private Map<String, Object> meta;
    // Rich data, inlined at creation time
    private String title;
    private String description;
    private Map<String, Object> context;
    private List<TaskReference> references;
    // Task-side geo constraint: with maxDistanceKm, the service radius workers must be inside
    private Double latitude;
    private Double longitude;
    private Double maxDistanceKm;
    private Boolean requireGeo;   // exclude workers without coordinates
    private List<String> allowedCidrs;

    public CreateTask(List<String> tags) {
        if (tags == null || tags.isEmpty()) {
            throw new IllegalArgumentException("tags must not be null or empty");
        }
        this.tags = tags;
    }

    public List<String> getTags() { return tags; }
    public String getId() { return id; }
    public Double getPriority() { return priority; }
    public Map<String, Double> getSkillThresholds() { return skillThresholds; }
    public List<String> getVetoedWorkers() { return vetoedWorkers; }
    public Map<String, Object> getMeta() { return meta; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
    public Double getMaxDistanceKm() { return maxDistanceKm; }
    public Boolean getRequireGeo() { return requireGeo; }
    public List<String> getAllowedCidrs() { return allowedCidrs; }

    public CreateTask id(String id) { this.id = id; return this; }
    public CreateTask priority(double priority) { this.priority = priority; return this; }
    public CreateTask skillThresholds(Map<String, Double> t) { this.skillThresholds = t; return this; }
    public CreateTask vetoedWorkers(List<String> vetoedWorkers) { this.vetoedWorkers = vetoedWorkers; return this; }
    public CreateTask meta(Map<String, Object> meta) { this.meta = meta; return this; }
    public CreateTask title(String title) { this.title = title; return this; }
    public CreateTask description(String description) { this.description = description; return this; }
    public CreateTask context(Map<String, Object> context) { this.context = context; return this; }
    public CreateTask references(List<TaskReference> references) { this.references = references; return this; }
    public CreateTask latitude(double latitude) { this.latitude = latitude; return this; }
    public CreateTask longitude(double longitude) { this.longitude = longitude; return this; }
    public CreateTask maxDistanceKm(double maxDistanceKm) { this.maxDistanceKm = maxDistanceKm; return this; }
    public CreateTask requireGeo(boolean requireGeo) { this.requireGeo = requireGeo; return this; }
    public CreateTask allowedCidrs(List<String> allowedCidrs) { this.allowedCidrs = allowedCidrs; return this; }

    /** Serialises only the fields that were set, so the body matches the contract. */
    public String toJson() {
        return Json.write(this);
    }
}
