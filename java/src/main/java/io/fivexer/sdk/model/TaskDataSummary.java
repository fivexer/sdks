package io.fivexer.sdk.model;

/**
 * Counts of the rich data hanging off a task; populated on single-task reads only.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskDataSummary {
    private boolean hasContext;
    private int referenceCount;
    private int attachmentCount;
    private int commentCount;

    public boolean isHasContext() { return hasContext; }
    public int getReferenceCount() { return referenceCount; }
    public int getAttachmentCount() { return attachmentCount; }
    public int getCommentCount() { return commentCount; }
}
