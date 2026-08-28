package io.fivexer.sdk.model;

/**
 * When a task may be offered.
 *
 * <p>Unlike escalation and SLA there is no workspace default to inherit — these timestamps are
 * absolute epoch-milliseconds.
 */
public class SchedulePolicy {

    private Long notBefore;
    private Long notAfter;
    private String onMiss;

    public SchedulePolicy() {}

    public Long getNotBefore() { return notBefore; }
    public Long getNotAfter() { return notAfter; }
    public String getOnMiss() { return onMiss; }

    /** Held out of matching until this moment; the task reads as {@code scheduled} until then. */
    public SchedulePolicy notBefore(long notBefore) { this.notBefore = notBefore; return this; }

    /** The offer window closes here; an unserved task is parked or dropped. */
    public SchedulePolicy notAfter(long notAfter) { this.notAfter = notAfter; return this; }

    /** {@code "park"} keeps a missed task for review, {@code "drop"} discards it. */
    public SchedulePolicy onMiss(String onMiss) { this.onMiss = onMiss; return this; }
}
