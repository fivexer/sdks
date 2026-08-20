package io.fivexer.sdk.model;

import java.util.List;

/**
 * A cursor-paginated page of runs.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowRunPage {
    private List<WorkflowRun> runs;
    private String nextCursor;

    public List<WorkflowRun> getRuns() { return runs; }
    public String getNextCursor() { return nextCursor; }
}
