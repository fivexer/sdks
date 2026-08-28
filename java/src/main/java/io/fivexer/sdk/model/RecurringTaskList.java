package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/tasks/recurring}. */
public class RecurringTaskList {

    private List<RecurringTask> recurring;
    private int count;

    public List<RecurringTask> getRecurring() { return recurring; }
    public int getCount() { return count; }
}
