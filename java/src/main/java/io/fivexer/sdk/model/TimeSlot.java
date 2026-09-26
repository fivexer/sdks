package io.fivexer.sdk.model;

/**
 * An appointment: the moment the work is performed and how long it takes.
 *
 * <p>A slotted task is held out of matching until {@code startAt}; ahead of that a worker's time
 * is reserved for it (a {@link SlotBooking}), which is the real answer to "who is on this".
 *
 * <p>Serves as both an input model ({@link CreateTask#slot}) and a response model
 * ({@link Task#getSlot}). Timestamps and durations are epoch-milliseconds / milliseconds.
 */
public class TimeSlot {
    private Long startAt;
    private Long durationMs;
    private Long bookAheadMs;
    private String onUnbooked;

    /** Response construction (Gson). */
    public TimeSlot() {}

    /**
     * @param startAt epoch ms at which the work is performed
     * @param durationMs how long it takes — at least a minute, at most 24 hours
     */
    public TimeSlot(long startAt, long durationMs) {
        this.startAt = startAt;
        this.durationMs = durationMs;
    }

    public Long getStartAt() { return startAt; }
    public Long getDurationMs() { return durationMs; }
    public Long getBookAheadMs() { return bookAheadMs; }
    public String getOnUnbooked() { return onUnbooked; }

    /**
     * How far ahead of {@code startAt} a worker may be reserved. Defaults to a week server-side.
     * Booking earlier gives people more notice; booking later sees a truer picture of who is free.
     */
    public TimeSlot bookAheadMs(long bookAheadMs) { this.bookAheadMs = bookAheadMs; return this; }

    /**
     * What happens if {@code startAt} arrives with nobody booked: {@code "queue"} (the default)
     * matches it like any other task, {@code "park"} holds it for an operator, {@code "drop"}
     * removes it.
     */
    public TimeSlot onUnbooked(String onUnbooked) { this.onUnbooked = onUnbooked; return this; }
}
