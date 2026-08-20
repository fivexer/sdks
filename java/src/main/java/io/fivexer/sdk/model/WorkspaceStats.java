package io.fivexer.sdk.model;

/** Result of {@code GET /v1/stats}: workspace counts, worker total, and the monthly meter. */
public class WorkspaceStats {
    private String plan;
    private java.util.Map<String, Integer> tasks;
    private int workers;
    private Meter meter;
    private QueueStats queue;
    private MatchingPolicy matching;

    public String getPlan() { return plan; }
    public java.util.Map<String, Integer> getTasks() { return tasks; }
    public int getWorkers() { return workers; }
    public Meter getMeter() { return meter; }

    /** Queue depth and per-worker load. Absent on data-plane-only deployments. */
    public QueueStats getQueue() { return queue; }

    /** The live balancer policy applied to bulk matching passes. */
    public MatchingPolicy getMatching() { return matching; }

    public static class Meter {
        private String period;
        private int matchedTasks;
        private int includedTasksPerMonth;

        public String getPeriod() { return period; }
        public int getMatchedTasks() { return matchedTasks; }
        public int getIncludedTasksPerMonth() { return includedTasksPerMonth; }
    }
}
