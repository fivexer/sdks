package io.fivexer.sdk.model;

/**
 * How much work the background sweeps still owe.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class QueueAuditSweepBacklog {
    private Integer scheduleActivations;
    private Integer scheduleMisses;
    private Integer responseDeadlines;
    private Integer completionDeadlines;
    private Integer slaExpiries;

    public Integer getScheduleActivations() { return scheduleActivations; }
    public Integer getScheduleMisses() { return scheduleMisses; }
    public Integer getResponseDeadlines() { return responseDeadlines; }
    public Integer getCompletionDeadlines() { return completionDeadlines; }
    public Integer getSlaExpiries() { return slaExpiries; }
}
