package io.fivexer.sdk.model;

import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
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
    private String validFrom;
    private String validUntil;

    // transient: Gson must not emit these flags as fields. They record the difference between
    // "leave the date alone" (absent) and "erase it" (explicit null), which a null String
    // cannot express on its own.
    private transient boolean clearValidFrom;
    private transient boolean clearValidUntil;

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
    public String getValidFrom() { return validFrom; }
    public String getValidUntil() { return validUntil; }

    public WorkerSkillAssignment weightOverride(Double weightOverride) { this.weightOverride = weightOverride; return this; }

    /** Inclusive ISO day ({@code YYYY-MM-DD}) the qualification becomes valid. */
    public WorkerSkillAssignment validFrom(String validFrom) {
        this.validFrom = validFrom;
        this.clearValidFrom = false;
        return this;
    }

    /** Erase a stored start date, making the qualification always held. Sends an explicit null. */
    public WorkerSkillAssignment clearValidFrom() {
        this.validFrom = null;
        this.clearValidFrom = true;
        return this;
    }

    /**
     * Inclusive <em>last</em> day it may be relied on. The roster checks this against the
     * <strong>shift's</strong> date, so a licence lapsing mid-period bars the shifts after it and
     * leaves the earlier ones standing.
     */
    public WorkerSkillAssignment validUntil(String validUntil) {
        this.validUntil = validUntil;
        this.clearValidUntil = false;
        return this;
    }

    /** Erase a stored expiry, making the qualification permanent. Sends an explicit null. */
    public WorkerSkillAssignment clearValidUntil() {
        this.validUntil = null;
        this.clearValidUntil = true;
        return this;
    }

    /**
     * Stamp this assignment's explicit nulls onto its already-serialised form.
     *
     * <p>Needed because an assignment is nested inside a worker's {@code skills} array, so Gson
     * reflects over it directly and {@link #toJson()} never runs for the nested case. The
     * enclosing input model calls this for each element after building its own tree.
     */
    void applyExplicitNulls(JsonObject target) {
        if (clearValidFrom) {
            target.add("validFrom", JsonNull.INSTANCE);
        }
        if (clearValidUntil) {
            target.add("validUntil", JsonNull.INSTANCE);
        }
    }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
