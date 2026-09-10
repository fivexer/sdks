package io.fivexer.sdk.model;

import com.google.gson.JsonArray;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
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
    private String locale;

    // transient: a flag, not a wire field. Distinguishes "leave the language alone"
    // (absent) from "erase it" (explicit null), which a null String cannot express alone.
    private transient boolean clearLocale;

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


    /**
     * The worker's own language — not only a portal preference: it is what their push
     * notifications and invitation email are composed in. Same column and validation
     * ({@code en}/{@code et}) as the worker's own {@code PATCH /portal/me}, so a manager setting
     * it here and the worker setting it themselves can never disagree about what a legal value is.
     */
    public PatchWorker locale(String locale) {
        this.locale = locale;
        this.clearLocale = false;
        return this;
    }

    /** Erase the stored language, returning them to the workspace default. Sends an explicit null. */
    public PatchWorker clearLocale() {
        this.locale = null;
        this.clearLocale = true;
        return this;
    }

    /**
     * Serialises only the fields that were set, then restores the explicit nulls that Gson's
     * omit-nulls rule cannot express: a cleared language, and a cleared validity date on any
     * nested skill assignment.
     */
    public String toJson() {
        JsonObject o = Json.tree(this).getAsJsonObject();
        if (clearLocale) {
            o.add("locale", JsonNull.INSTANCE);
        }
        if (skills != null && o.has("skills")) {
            JsonArray out = o.getAsJsonArray("skills");
            for (int i = 0; i < skills.size(); i++) {
                skills.get(i).applyExplicitNulls(out.get(i).getAsJsonObject());
            }
        }
        return o.toString();
    }
}
