package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * The rich data stored against a task: title, description, free-form context and references.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskContext {
    private String taskId;
    private String title;
    private String description;
    private Map<String, Object> context;
    private List<TaskReference> references;
    private Long createdAt;
    private Long updatedAt;

    public String getTaskId() { return taskId; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }
    public Long getCreatedAt() { return createdAt; }
    public Long getUpdatedAt() { return updatedAt; }
}
