package io.fivexer.sdk.model;

import java.util.List;

/**
 * The recorded shift and break log for one worker over a window — the working-time record an
 * EU employer must keep (CJEU C-55/18).
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTimeEntriesResult {
    private String workerId;
    private String from;
    private String to;
    private List<WorkerTimeEntry> entries;
    private WorkerTimeTotals totals;

    public String getWorkerId() { return workerId; }
    public String getFrom() { return from; }
    public String getTo() { return to; }
    public List<WorkerTimeEntry> getEntries() { return entries; }
    public WorkerTimeTotals getTotals() { return totals; }
}
