package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/tasks/bookings}. */
public class SlotBookingList {

    private List<SlotBooking> bookings;
    private int count;

    public List<SlotBooking> getBookings() { return bookings; }
    public int getCount() { return count; }
}
