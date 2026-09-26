package io.fivexer.sdk.model;

import java.util.List;

/**
 * Worked time, breaks and outcomes per worker (and per day, when entries were requested) for a
 * window — the team's working-time report. Days are UTC.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TeamTimeResult {
    private String from;
    private String to;
    private List<TeamTimeWorker> workers;
    private List<TeamTimeDay> days;
    private Totals totals;
    private boolean truncated;

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public List<TeamTimeWorker> getWorkers() { return workers; }

    /** Null unless {@code entries} was requested — the series is derived from the log. */
    public List<TeamTimeDay> getDays() { return days; }

    public Totals getTotals() { return totals; }

    /** The window held more log rows than one read returns; the record is incomplete. */
    public boolean isTruncated() { return truncated; }

    /** The window's totals across every worker in it. */
    public static class Totals {
        private int workerCount;
        private int shiftCount;
        private long onShiftMs;
        private int breakCount;
        private long breakMs;
        private long workingMs;
        private int completed;
        private int offered;
        private int accepted;
        private int rejected;
        private int expired;
        private int failed;

        public int getWorkerCount() { return workerCount; }
        public int getShiftCount() { return shiftCount; }
        public long getOnShiftMs() { return onShiftMs; }
        public int getBreakCount() { return breakCount; }
        public long getBreakMs() { return breakMs; }
        public long getWorkingMs() { return workingMs; }
        public int getCompleted() { return completed; }
        public int getOffered() { return offered; }
        public int getAccepted() { return accepted; }
        public int getRejected() { return rejected; }
        public int getExpired() { return expired; }
        public int getFailed() { return failed; }
    }
}
