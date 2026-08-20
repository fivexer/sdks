package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Assign a skill to a worker. {@code level} is 1-5; the API validates the range.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class WorkerSkillAssignment {
    private String skillId;
    private Integer level;
    private Double weightOverride;

    public WorkerSkillAssignment(String skillId, Integer level) {
        if (skillId == null) {
            throw new IllegalArgumentException("skillId is required");
        }
        if (level == null) {
            throw new IllegalArgumentException("level is required");
        }
        this.skillId = skillId;
        this.level = level;
    }

    public String getSkillId() { return skillId; }
    public Integer getLevel() { return level; }
    public Double getWeightOverride() { return weightOverride; }

    public WorkerSkillAssignment weightOverride(Double weightOverride) { this.weightOverride = weightOverride; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
