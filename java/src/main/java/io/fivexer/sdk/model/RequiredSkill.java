package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * A minimum skill level a candidate must hold.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class RequiredSkill {
    private String skillId;
    private Integer minLevel;

    public RequiredSkill(String skillId, Integer minLevel) {
        if (skillId == null) {
            throw new IllegalArgumentException("skillId is required");
        }
        if (minLevel == null) {
            throw new IllegalArgumentException("minLevel is required");
        }
        this.skillId = skillId;
        this.minLevel = minLevel;
    }

    public String getSkillId() { return skillId; }
    public Integer getMinLevel() { return minLevel; }



    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
