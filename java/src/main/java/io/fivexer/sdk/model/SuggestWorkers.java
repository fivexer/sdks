package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Dry-run scoring: who <em>would</em> match these tags, without creating a task.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class SuggestWorkers {
    private List<String> tags;
    private Double priority;
    private List<RequiredSkill> requiredSkills;
    private List<String> vetoedWorkers;
    private Integer limit;
    private Double latitude;
    private Double longitude;
    private Double maxDistanceKm;
    private Boolean requireGeo;
    private List<String> allowedCidrs;

    public SuggestWorkers(List<String> tags) {
        if (tags == null) {
            throw new IllegalArgumentException("tags is required");
        }
        this.tags = tags;
    }

    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public List<RequiredSkill> getRequiredSkills() { return requiredSkills; }
    public List<String> getVetoedWorkers() { return vetoedWorkers; }
    public Integer getLimit() { return limit; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
    public Double getMaxDistanceKm() { return maxDistanceKm; }
    public Boolean getRequireGeo() { return requireGeo; }
    public List<String> getAllowedCidrs() { return allowedCidrs; }

    public SuggestWorkers priority(Double priority) { this.priority = priority; return this; }
    public SuggestWorkers requiredSkills(List<RequiredSkill> requiredSkills) { this.requiredSkills = requiredSkills; return this; }
    public SuggestWorkers vetoedWorkers(List<String> vetoedWorkers) { this.vetoedWorkers = vetoedWorkers; return this; }
    public SuggestWorkers limit(Integer limit) { this.limit = limit; return this; }
    public SuggestWorkers latitude(Double latitude) { this.latitude = latitude; return this; }
    public SuggestWorkers longitude(Double longitude) { this.longitude = longitude; return this; }
    public SuggestWorkers maxDistanceKm(Double maxDistanceKm) { this.maxDistanceKm = maxDistanceKm; return this; }
    public SuggestWorkers requireGeo(Boolean requireGeo) { this.requireGeo = requireGeo; return this; }
    public SuggestWorkers allowedCidrs(List<String> allowedCidrs) { this.allowedCidrs = allowedCidrs; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
