package io.fivexer.sdk.model;

/**
 * One recorded stretch of a worker's time — a shift, or a break taken inside one.
 *
 * <p>{@code endReason} {@code "timeout"} means the platform clocked out an unattended worker that
 * went silent past the liveness contract it declared when going on shift; it is not a judgement
 * about the person.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTimeEntry {
    private String type;
    private String startedAt;
    private String endedAt;
    private long durationMs;
    private String source;
    private String endReason;
    private String reason;

    /** {@code "shift"} or {@code "break"}. */
    public String getType() { return type; }

    public String getStartedAt() { return startedAt; }

    /** Null while the shift or break is still open. */
    public String getEndedAt() { return endedAt; }

    public long getDurationMs() { return durationMs; }

    /** Shifts only: who opened it — {@code portal}, {@code operator} or {@code supervisor}. */
    public String getSource() { return source; }

    /** Shifts only: {@code manual}, {@code timeout} or {@code removed}; null while open. */
    public String getEndReason() { return endReason; }

    /** Breaks only: the worker's stated reason. */
    public String getReason() { return reason; }
}
