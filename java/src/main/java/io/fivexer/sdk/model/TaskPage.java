package io.fivexer.sdk.model;

import java.util.List;

/**
 * A page of tasks from {@code GET /v1/tasks}. Use {@link #getNextCursor()} with the next
 * request's {@code cursor} to paginate while {@link #isHasMore()} is true.
 */
public class TaskPage {
    private List<Task> tasks;
    private String nextCursor;
    private boolean hasMore;

    public List<Task> getTasks() { return tasks; }
    public String getNextCursor() { return nextCursor; }
    public boolean isHasMore() { return hasMore; }
}
