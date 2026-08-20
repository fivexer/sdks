package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Partial update of a team — absent fields keep their stored value. The {@code key} is immutable, because the derived routing tag would change under live matching.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class PatchTeam {
    private String name;
    private String description;
    private String color;

    public PatchTeam() {}

    public String getName() { return name; }
    public String getDescription() { return description; }
    public String getColor() { return color; }

    public PatchTeam name(String name) { this.name = name; return this; }
    public PatchTeam description(String description) { this.description = description; return this; }
    public PatchTeam color(String color) { this.color = color; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
