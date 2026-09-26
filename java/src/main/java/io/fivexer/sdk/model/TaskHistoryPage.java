package io.fivexer.sdk.model;

import java.util.List;

/** One page of {@code GET /v1/tasks/history}, newest first. */
public class TaskHistoryPage {
    private List<ArchivedTask> tasks;
    private String nextCursor;
    private boolean hasMore;

    public List<ArchivedTask> getTasks() { return tasks; }

    /** Opaque; pass back as the query's {@code cursor}. Null on the last page. */
    public String getNextCursor() { return nextCursor; }

    public boolean isHasMore() { return hasMore; }
}
