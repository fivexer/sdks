package io.fivexer.sdk.model;

import java.util.List;

/**
 * A worker's own breaks and completions for today.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerBreakToday {
    private String workerId;
    private String since;
    private List<WorkerBreak> breaks;
    private WorkerBreak active;  // the open break, if one is running right now
    private int completedTasks;
    private long totalBreakMs;

    public String getWorkerId() { return workerId; }
    public String getSince() { return since; }
    public List<WorkerBreak> getBreaks() { return breaks; }
    public WorkerBreak getActive() { return active; }
    public int getCompletedTasks() { return completedTasks; }
    public long getTotalBreakMs() { return totalBreakMs; }
}
