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
    private ResultContract resultContract;
    private Long createdAt;
    private Long updatedAt;
    private String contentState;

    public String getTaskId() { return taskId; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }
    /** The requester's definition of done, fixed at creation; null when the task has none. */
    public ResultContract getResultContract() { return resultContract; }
    public Long getCreatedAt() { return createdAt; }
    public Long getUpdatedAt() { return updatedAt; }

    /**
     * Short-lived content that cannot be shown, and why: {@code expired} (the window passed) or
     * {@code withheld} (this caller may not read it). Null when shown as stored.
     */
    public String getContentState() { return contentState; }
}
