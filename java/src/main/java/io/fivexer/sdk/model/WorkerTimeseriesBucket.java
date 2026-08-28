package io.fivexer.sdk.model;

/**
 * One bucket of a single worker's series.
 *
 * <p>Worked time and the lifecycle counters are day-grained, so they are present only on
 * {@code bucket=day} responses. They are null on hour buckets rather than zero: "not measured at
 * this resolution" is not the same answer as "measured as nothing".
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTimeseriesBucket extends StatsTimeseriesBucket {
    private Long onShiftMs;
    private Long breakMs;
    private Long workingMs;
    private Integer offered;
    private Integer accepted;
    private Integer rejected;
    private Integer failed;
    private Integer expired;
    private Integer released;

    /** Time on shift in this bucket, breaks included. Day buckets only. */
    public Long getOnShiftMs() { return onShiftMs; }

    public Long getBreakMs() { return breakMs; }

    /** {@code onShiftMs - breakMs} — worked time. Day buckets only. */
    public Long getWorkingMs() { return workingMs; }

    public Integer getOffered() { return offered; }
    public Integer getAccepted() { return accepted; }
    public Integer getRejected() { return rejected; }
    public Integer getFailed() { return failed; }
    public Integer getExpired() { return expired; }
    public Integer getReleased() { return released; }
}
