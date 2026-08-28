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
    // Hard skill gate in catalog terms — the typed twin of skillThresholds
    private List<RequiredSkill> requiredSkills;
    // Policies. Absent inherits the workspace default; see escalationNone()/slaNone() to opt out
    private EscalationPolicy escalation;
    private SlaPolicy sla;
    private SchedulePolicy schedule;
    private RecurrencePolicy recurrence;
    private String teamId;
    private String preferTeamId;
    // Not serialised by Gson: these record the *request* for an explicit null, which toJson()
    // writes onto the tree. A `transient` field is invisible to Gson, which is exactly right —
    // the marker is SDK bookkeeping, never a wire field of its own.
    private transient boolean escalationNull;
    private transient boolean slaNull;

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
    public List<RequiredSkill> getRequiredSkills() { return requiredSkills; }
    public EscalationPolicy getEscalation() { return escalation; }
    public SlaPolicy getSla() { return sla; }
    public SchedulePolicy getSchedule() { return schedule; }
    public RecurrencePolicy getRecurrence() { return recurrence; }
    public String getTeamId() { return teamId; }
    public String getPreferTeamId() { return preferTeamId; }

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

    /** Minimum catalog skill levels a worker must hold to be eligible. */
    public CreateTask requiredSkills(List<RequiredSkill> requiredSkills) {
        this.requiredSkills = requiredSkills;
        return this;
    }

    /**
     * Response clock for this task. Omitting it inherits the workspace default; to opt out of
     * that default entirely, call {@link #escalationNone()} — an absent field and an explicit
     * null are different instructions to the server.
     */
    public CreateTask escalation(EscalationPolicy escalation) { this.escalation = escalation; return this; }

    /** Send {@code "escalation": null} — opt this task out of the workspace default. */
    public CreateTask escalationNone() { this.escalation = null; this.escalationNull = true; return this; }

    /** Completion clock, shelf life and rejection budget. Same inheritance rule as escalation. */
    public CreateTask sla(SlaPolicy sla) { this.sla = sla; return this; }

    /** Send {@code "sla": null} — opt this task out of the workspace default. */
    public CreateTask slaNone() { this.sla = null; this.slaNull = true; return this; }

    /** When this task may be offered. No workspace default to inherit — timestamps are absolute. */
    public CreateTask schedule(SchedulePolicy schedule) { this.schedule = schedule; return this; }

    /** Make this a standing template instead of a one-off. Mutually exclusive with a schedule. */
    public CreateTask recurrence(RecurrencePolicy recurrence) { this.recurrence = recurrence; return this; }

    /** Hard team gate: only members are eligible. Mutually exclusive with {@link #preferTeamId}. */
    public CreateTask teamId(String teamId) { this.teamId = teamId; return this; }

    /** Soft team preference: members rank first, everyone else stays eligible. */
    public CreateTask preferTeamId(String preferTeamId) { this.preferTeamId = preferTeamId; return this; }

    /**
     * Serialises only the fields that were set, so the body matches the contract.
     *
     * <p>The two nullable policies are the exception to Gson's omit-nulls rule: an <em>absent</em>
     * escalation or SLA inherits the workspace default, while an explicit null opts out of it.
     * Gson cannot express the second, so the opt-out is written onto the tree afterwards.
     */
    public String toJson() {
        if (!escalationNull && !slaNull) {
            return Json.write(this);
        }
        com.google.gson.JsonObject body = Json.tree(this).getAsJsonObject();
        if (escalationNull) {
            body.add("escalation", com.google.gson.JsonNull.INSTANCE);
        }
        if (slaNull) {
            body.add("sla", com.google.gson.JsonNull.INSTANCE);
        }
        return body.toString();
    }
}
