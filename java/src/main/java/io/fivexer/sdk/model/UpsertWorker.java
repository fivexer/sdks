package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * Input for {@code POST /v1/workers}. Every field is optional — an empty body asks the API to
 * generate the worker id.
 */
public class UpsertWorker {
    private String id;
    private List<String> tags;
    private Map<String, Double> routingWeights;
    private List<WorkerSkillAssignment> skills;
    private String ip;
    private Double latitude;
    private Double longitude;
    private Double maxTravelDistanceKm;
    private Integer maxBacklogSize;  // per-worker cap overriding the workspace default; 0 = receive nothing

    public UpsertWorker() {}

    public UpsertWorker(String id) {
        this.id = id;
    }

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Map<String, Double> getRoutingWeights() { return routingWeights; }
    public List<WorkerSkillAssignment> getSkills() { return skills; }
    public String getIp() { return ip; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
    public Double getMaxTravelDistanceKm() { return maxTravelDistanceKm; }
    public Integer getMaxBacklogSize() { return maxBacklogSize; }

    public UpsertWorker id(String id) { this.id = id; return this; }
    public UpsertWorker tags(List<String> tags) { this.tags = tags; return this; }
    public UpsertWorker routingWeights(Map<String, Double> w) { this.routingWeights = w; return this; }
    public UpsertWorker skills(List<WorkerSkillAssignment> skills) { this.skills = skills; return this; }
    public UpsertWorker ip(String ip) { this.ip = ip; return this; }
    public UpsertWorker latitude(double latitude) { this.latitude = latitude; return this; }
    public UpsertWorker longitude(double longitude) { this.longitude = longitude; return this; }
    public UpsertWorker maxTravelDistanceKm(double km) { this.maxTravelDistanceKm = km; return this; }
    public UpsertWorker maxBacklogSize(int maxBacklogSize) { this.maxBacklogSize = maxBacklogSize; return this; }

    /** Serialises only the fields that were set. */
    public String toJson() {
        return Json.write(this);
    }
}
