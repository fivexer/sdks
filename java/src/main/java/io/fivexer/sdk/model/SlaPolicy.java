package io.fivexer.sdk.model;

/**
 * The completion clock, the shelf life and the rejection budget.
 *
 * <p>Complements {@link EscalationPolicy} rather than overlapping it: escalation owns the
 * <em>response</em> clock, SLA owns everything after. An SLA never gates matching eligibility —
 * a breach is reported and acted on, it does not make the task unmatchable.
 */
public class SlaPolicy {

    private Long completeWithinMs;
    private Long expireAfterMs;
    private Integer maxRejections;
    private String onCompletionBreach;
    private String onMaxRejections;
    private String onExpire;

    public SlaPolicy() {}

    public Long getCompleteWithinMs() { return completeWithinMs; }
    public Long getExpireAfterMs() { return expireAfterMs; }
    public Integer getMaxRejections() { return maxRejections; }
    public String getOnCompletionBreach() { return onCompletionBreach; }
    public String getOnMaxRejections() { return onMaxRejections; }
    public String getOnExpire() { return onExpire; }

    /** Completion deadline, measured from acceptance. Breaches fire once. */
    public SlaPolicy completeWithinMs(long completeWithinMs) { this.completeWithinMs = completeWithinMs; return this; }

    /** Shelf life from first enqueue — never extended by a requeue. */
    public SlaPolicy expireAfterMs(long expireAfterMs) { this.expireAfterMs = expireAfterMs; return this; }

    /** Rejections allowed before {@link #onMaxRejections}; outranks the escalation ladder. */
    public SlaPolicy maxRejections(int maxRejections) { this.maxRejections = maxRejections; return this; }

    /** {@code "notify"} | {@code "requeue"} | {@code "fail"} | {@code "park"} */
    public SlaPolicy onCompletionBreach(String onCompletionBreach) {
        this.onCompletionBreach = onCompletionBreach;
        return this;
    }

    /** {@code "park"} | {@code "fail"} | {@code "keep"} */
    public SlaPolicy onMaxRejections(String onMaxRejections) { this.onMaxRejections = onMaxRejections; return this; }

    /** {@code "drop"} | {@code "park"} */
    public SlaPolicy onExpire(String onExpire) { this.onExpire = onExpire; return this; }
}
