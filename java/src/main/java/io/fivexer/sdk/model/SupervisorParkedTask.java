package io.fivexer.sdk.model;

import java.util.List;

/**
 * A parked task as the board shows it — enough to decide on, not the whole task.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorParkedTask {
    private String id;
    private List<String> tags;
    private Double priority;
    private Long createdAt;

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public Long getCreatedAt() { return createdAt; }
}
