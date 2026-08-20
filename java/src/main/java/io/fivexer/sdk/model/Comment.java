package io.fivexer.sdk.model;

/**
 * A comment on a task.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class Comment {
    private String id;
    private String taskId;
    private CommentAuthor author;
    private String body;
    private long createdAt;

    public String getId() { return id; }
    public String getTaskId() { return taskId; }
    public CommentAuthor getAuthor() { return author; }
    public String getBody() { return body; }
    public long getCreatedAt() { return createdAt; }
}
