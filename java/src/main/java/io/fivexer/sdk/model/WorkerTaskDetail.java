package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * Rich detail for a task assigned to the authenticated worker (portal surface only).
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTaskDetail {
    private String id;
    private String status;
    private List<String> tags;
    private Double priority;
    private String title;
    private String description;
    private Map<String, Object> context;
    private List<TaskReference> references;
    private Long createdAt;

    public String getId() { return id; }
    public String getStatus() { return status; }
    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }
    public Long getCreatedAt() { return createdAt; }
}
