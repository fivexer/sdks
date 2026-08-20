package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for defining a skill.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class UpsertSkill {
    private String key;
    private String name;
    private String description;

    public UpsertSkill(String key, String name) {
        if (key == null) {
            throw new IllegalArgumentException("key is required");
        }
        if (name == null) {
            throw new IllegalArgumentException("name is required");
        }
        this.key = key;
        this.name = name;
    }

    public String getKey() { return key; }
    public String getName() { return name; }
    public String getDescription() { return description; }

    public UpsertSkill description(String description) { this.description = description; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
