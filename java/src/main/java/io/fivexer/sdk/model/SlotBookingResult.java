package io.fivexer.sdk.model;

/**
 * The result of booking a slot for a worker: the task id and the reservation made.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SlotBookingResult {
    private String id;
    private SlotBooking booking;

    public String getId() { return id; }
    public SlotBooking getBooking() { return booking; }
}
