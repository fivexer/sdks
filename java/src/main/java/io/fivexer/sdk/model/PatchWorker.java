package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * Partial in-place worker update — absent fields keep their stored value.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class PatchWorker {
    private List<String> tags;
    private Map<String, Double> routingWeights;
    private List<WorkerSkillAssignment> skills;
    private String ip;
    private Double latitude;
    private Double longitude;
    private Double maxTravelDistanceKm;
    private Integer maxBacklogSize;  // per-worker backlog cap; 0 = receive nothing

    public PatchWorker() {}

    public List<String> getTags() { return tags; }
    public Map<String, Double> getRoutingWeights() { return routingWeights; }
    public List<WorkerSkillAssignment> getSkills() { return skills; }
    public String getIp() { return ip; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
    public Double getMaxTravelDistanceKm() { return maxTravelDistanceKm; }
    public Integer getMaxBacklogSize() { return maxBacklogSize; }

    public PatchWorker tags(List<String> tags) { this.tags = tags; return this; }
    public PatchWorker routingWeights(Map<String, Double> routingWeights) { this.routingWeights = routingWeights; return this; }
    public PatchWorker skills(List<WorkerSkillAssignment> skills) { this.skills = skills; return this; }
    public PatchWorker ip(String ip) { this.ip = ip; return this; }
    public PatchWorker latitude(Double latitude) { this.latitude = latitude; return this; }
    public PatchWorker longitude(Double longitude) { this.longitude = longitude; return this; }
    public PatchWorker maxTravelDistanceKm(Double maxTravelDistanceKm) { this.maxTravelDistanceKm = maxTravelDistanceKm; return this; }
    public PatchWorker maxBacklogSize(Integer maxBacklogSize) { this.maxBacklogSize = maxBacklogSize; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
