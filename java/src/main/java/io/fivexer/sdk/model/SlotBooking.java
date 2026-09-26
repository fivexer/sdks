package io.fivexer.sdk.model;

import java.util.List;

/**
 * A worker's time reserved for a slotted task. At {@code startAt} the task is handed to whoever
 * holds the booking.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SlotBooking {
    private String taskId;
    private String workerId;
    private long startAt;
    private long endAt;
    private long bookedAt;
    private String source;
    private List<SlotConflict> warnings;

    public String getTaskId() { return taskId; }
    public String getWorkerId() { return workerId; }

    /** Epoch ms. */
    public long getStartAt() { return startAt; }

    /** Epoch ms. */
    public long getEndAt() { return endAt; }

    /** When the reservation was made (epoch ms). */
    public long getBookedAt() { return bookedAt; }

    /** {@code "sweep"} booked it automatically; {@code "manual"} is a planner's own call. */
    public String getSource() { return source; }

    /**
     * Soft conflicts the booking was made over, so a planner reviewing the week sees which
     * bookings are worth a second look. Null when there were none. An approved absence is never
     * here: it refuses the booking instead.
     */
    public List<SlotConflict> getWarnings() { return warnings; }
}
