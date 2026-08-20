package io.fivexer.sdk.model;

import java.util.List;

/**
 * An unpaginated task listing — the parked and scheduled views return the whole set.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskList {
    private List<Task> tasks;
    private Integer count;

    public List<Task> getTasks() { return tasks; }
    public Integer getCount() { return count; }
}
