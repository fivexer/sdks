package io.fivexer.sdk.model;

import java.util.List;

/**
 * Why the queue is not draining. Walks the queue against the live roster, so it is heavier than {@link WorkspaceStats} — poll it on a dashboard's cadence, not a request's.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class QueueAuditReport {
    private Long evaluatedAt;
    private Integer scanned;
    private List<QueueAuditEntry> entries;
    private QueueAuditSweepBacklog sweepBacklog;

    public Long getEvaluatedAt() { return evaluatedAt; }
    public Integer getScanned() { return scanned; }
    public List<QueueAuditEntry> getEntries() { return entries; }
    public QueueAuditSweepBacklog getSweepBacklog() { return sweepBacklog; }
}
