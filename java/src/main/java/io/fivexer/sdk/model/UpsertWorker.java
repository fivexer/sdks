package io.fivexer.sdk.model;

import com.google.gson.JsonArray;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
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
    private String locale;

    // transient: a flag, not a wire field. Distinguishes "leave the language alone"
    // (absent) from "erase it" (explicit null), which a null String cannot express alone.
    private transient boolean clearLocale;
    private List<String> teamIds;    // replaces membership wholesale; empty list clears it
    private Boolean available;       // shift state; omit to leave a worker's current state alone

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
    public List<String> getTeamIds() { return teamIds; }
    public Boolean getAvailable() { return available; }

    public UpsertWorker id(String id) { this.id = id; return this; }
    public UpsertWorker tags(List<String> tags) { this.tags = tags; return this; }
    public UpsertWorker routingWeights(Map<String, Double> w) { this.routingWeights = w; return this; }
    public UpsertWorker skills(List<WorkerSkillAssignment> skills) { this.skills = skills; return this; }
    public UpsertWorker ip(String ip) { this.ip = ip; return this; }
    public UpsertWorker latitude(double latitude) { this.latitude = latitude; return this; }
    public UpsertWorker longitude(double longitude) { this.longitude = longitude; return this; }
    public UpsertWorker maxTravelDistanceKm(double km) { this.maxTravelDistanceKm = km; return this; }
    public UpsertWorker maxBacklogSize(int maxBacklogSize) { this.maxBacklogSize = maxBacklogSize; return this; }

    /**
     * Replaces team membership wholesale. Omit to leave it untouched; an empty list clears it —
     * the only way to remove a worker from every team.
     */
    public UpsertWorker teamIds(List<String> teamIds) { this.teamIds = teamIds; return this; }

    /**
     * Shift state. A <em>new</em> worker is created off shift and is not matched until they go
     * available from the portal or an operator resumes them — availability is a claim a person
     * makes, not a side effect of existing. Pass {@code true} here to create an already-available
     * worker in one call: the escape hatch for programmatic fleets with no human at a portal. On
     * an update, omit it to leave the worker's current shift state untouched.
     */
    public UpsertWorker available(boolean available) { this.available = available; return this; }


    /**
     * The worker's own language — not only a portal preference: it is what their push
     * notifications and invitation email are composed in. Same column and validation
     * ({@code en}/{@code et}) as the worker's own {@code PATCH /portal/me}, so a manager setting
     * it here and the worker setting it themselves can never disagree about what a legal value is.
     */
    public UpsertWorker locale(String locale) {
        this.locale = locale;
        this.clearLocale = false;
        return this;
    }

    /** Erase the stored language, returning them to the workspace default. Sends an explicit null. */
    public UpsertWorker clearLocale() {
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
