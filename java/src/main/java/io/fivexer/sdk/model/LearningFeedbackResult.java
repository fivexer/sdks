package io.fivexer.sdk.model;

/**
 * Bulk feedback is partial-success: each item reports its own outcome.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class LearningFeedbackResult {
    private String taskId;
    private boolean ok;
    private String error;

    public String getTaskId() { return taskId; }
    public boolean isOk() { return ok; }
    public String getError() { return error; }
}
