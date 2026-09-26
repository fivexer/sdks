package io.fivexer.sdk.model;

/**
 * A stretch of a worker's time that conflicts with a slot — an approved absence that refuses a
 * booking, or a soft conflict (outside a rostered shift, a busy calendar) that allows it.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SlotConflict {
    private long from;
    private long to;
    private String reason;

    /** Epoch ms. */
    public long getFrom() { return from; }

    /** Epoch ms. */
    public long getTo() { return to; }

    public String getReason() { return reason; }
}
