package io.fivexer.sdk.model;

import java.util.List;

/**
 * A stored notification sequence.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class NotificationSequence {
    private String id;
    private String name;
    private boolean enabled;
    private List<NotificationSequenceStep> steps;
    private List<String> filterTags;
    private String createdAt;  // ISO-8601
    private String updatedAt;  // ISO-8601

    public String getId() { return id; }
    public String getName() { return name; }
    public boolean isEnabled() { return enabled; }
    public List<NotificationSequenceStep> getSteps() { return steps; }
    public List<String> getFilterTags() { return filterTags; }
    public String getCreatedAt() { return createdAt; }
    public String getUpdatedAt() { return updatedAt; }
}
