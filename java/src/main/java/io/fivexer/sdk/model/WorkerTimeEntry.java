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
    private String id;
    private String type;
    private String startedAt;
    private String endedAt;
    private long durationMs;
    private boolean corrected;
    private String correctionNote;
    private String correctedBy;
    /**
     * Whether the record was changed or stated from nothing. An added shift was
     * never adjusted, and saying so sends the worker looking for an original
     * that never existed. Portal view only.
     */
    private String correctionKind;
    private String correctedAt;
    private String source;
    private String endReason;
    private String reason;

    /**
     * The row's own id — what a correction addresses. A wrong figure is disputed by row, never
     * by its times, which are the thing in dispute.
     */
    public String getId() { return id; }

    /** {@code "shift"} or {@code "break"}. */
    public String getType() { return type; }

    public String getStartedAt() { return startedAt; }

    /** Null while the shift or break is still open. */
    public String getEndedAt() { return endedAt; }

    public long getDurationMs() { return durationMs; }

    /** The times were changed after the fact; the correction says by whom and why. */
    public boolean isCorrected() { return corrected; }

    /**
     * Why it was changed, and who changed it. Carried on the per-worker log and on the worker's
     * own portal view — a person can always read the reason for a change to their own record —
     * but not on the team board, which lists many people and answers who was here.
     */
    public String getCorrectionNote() { return correctionNote; }

    public String getCorrectedBy() { return correctedBy; }
    public String getCorrectionKind() { return correctionKind; }

    public String getCorrectedAt() { return correctedAt; }

    /** Shifts only: who opened it — {@code portal}, {@code operator} or {@code supervisor}. */
    public String getSource() { return source; }

    /** Shifts only: {@code manual}, {@code timeout} or {@code removed}; null while open. */
    public String getEndReason() { return endReason; }

    /** Breaks only: the worker's stated reason. */
    public String getReason() { return reason; }
}
