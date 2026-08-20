package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * One skill a worker claims. Levels are 1-5; the routing weight they project to is the server's business.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class WorkerSkillLevel {
    private String skillId;
    private Integer level;

    public WorkerSkillLevel(String skillId, Integer level) {
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

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
