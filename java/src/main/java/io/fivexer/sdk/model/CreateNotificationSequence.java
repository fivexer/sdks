package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for creating a notification sequence.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class CreateNotificationSequence {
    private String name;
    private List<NotificationSequenceStep> steps;
    private Boolean enabled;
    private List<String> filterTags;

    public CreateNotificationSequence(String name, List<NotificationSequenceStep> steps) {
        if (name == null) {
            throw new IllegalArgumentException("name is required");
        }
        if (steps == null) {
            throw new IllegalArgumentException("steps is required");
        }
        this.name = name;
        this.steps = steps;
    }

    public String getName() { return name; }
    public List<NotificationSequenceStep> getSteps() { return steps; }
    public Boolean getEnabled() { return enabled; }
    public List<String> getFilterTags() { return filterTags; }

    public CreateNotificationSequence enabled(Boolean enabled) { this.enabled = enabled; return this; }
    public CreateNotificationSequence filterTags(List<String> filterTags) { this.filterTags = filterTags; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
