package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Partial skill update — the {@code key} is immutable.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class PatchSkill {
    private String name;
    private String description;

    public PatchSkill() {}

    public String getName() { return name; }
    public String getDescription() { return description; }

    public PatchSkill name(String name) { this.name = name; return this; }
    public PatchSkill description(String description) { this.description = description; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
