package io.fivexer.sdk.model;

/**
 * How a recurring task repeats.
 *
 * <p>A task created with a recurrence becomes a standing <em>template</em>, never itself
 * matchable: the platform materializes each occurrence as an ordinary scheduled task one
 * interval ahead of its window. Occurrences align to {@code startAt + k × everyMs} and never
 * drift, so a template that was down for an hour resumes on the original grid rather than an
 * hour late.
 */
public class RecurrencePolicy {

    private Long everyMs;
    private Long startAt;
    private Long windowMs;
    private String onMiss;
    private Long until;
    private Integer maxOccurrences;
    private String catchUp;

    /** @param everyMs milliseconds between one occurrence's window opening and the next (min 60s) */
    public RecurrencePolicy(long everyMs) {
        this.everyMs = everyMs;
    }

    /** For Gson, which reads a recurrence back off a template. */
    RecurrencePolicy() {}

    public Long getEveryMs() { return everyMs; }
    public Long getStartAt() { return startAt; }
    public Long getWindowMs() { return windowMs; }
    public String getOnMiss() { return onMiss; }
    public Long getUntil() { return until; }
    public Integer getMaxOccurrences() { return maxOccurrences; }
    public String getCatchUp() { return catchUp; }

    /** Epoch ms the first window opens. Default: now. */
    public RecurrencePolicy startAt(long startAt) { this.startAt = startAt; return this; }

    /** Offer window per occurrence; must be shorter than {@code everyMs}. */
    public RecurrencePolicy windowMs(long windowMs) { this.windowMs = windowMs; return this; }

    /** What an unserved window does to that occurrence: {@code "park"} | {@code "drop"}. */
    public RecurrencePolicy onMiss(String onMiss) { this.onMiss = onMiss; return this; }

    /** No occurrence opens after this epoch ms; the template retires. */
    public RecurrencePolicy until(long until) { this.until = until; return this; }

    public RecurrencePolicy maxOccurrences(int maxOccurrences) {
        this.maxOccurrences = maxOccurrences;
        return this;
    }

    /**
     * Downtime policy. {@code "skip"} (default) resumes the cadence without back-filling fully
     * elapsed slots; {@code "all"} materializes them to be parked or dropped as missed — the
     * audit-trail reading of a dead interval.
     */
    public RecurrencePolicy catchUp(String catchUp) { this.catchUp = catchUp; return this; }
}
