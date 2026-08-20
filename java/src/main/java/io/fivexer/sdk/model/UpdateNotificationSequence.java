package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Partial sequence update — absent fields keep their stored value.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class UpdateNotificationSequence {
    private String name;
    private Boolean enabled;
    private List<NotificationSequenceStep> steps;
    private List<String> filterTags;

    public UpdateNotificationSequence() {}

    public String getName() { return name; }
    public Boolean getEnabled() { return enabled; }
    public List<NotificationSequenceStep> getSteps() { return steps; }
    public List<String> getFilterTags() { return filterTags; }

    public UpdateNotificationSequence name(String name) { this.name = name; return this; }
    public UpdateNotificationSequence enabled(Boolean enabled) { this.enabled = enabled; return this; }
    public UpdateNotificationSequence steps(List<NotificationSequenceStep> steps) { this.steps = steps; return this; }
    public UpdateNotificationSequence filterTags(List<String> filterTags) { this.filterTags = filterTags; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
