package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * One step of a notification sequence: fire {@code eventType} at {@code offsetMs} after
 * {@code trigger}. Used both when creating a sequence and when reading one back.
 */
public class NotificationSequenceStep {
    private String trigger;   // matched | expiring | expired | accepted | rejected | completed
    private Long offsetMs;
    private String eventType;

    /** Response construction (Gson). */
    public NotificationSequenceStep() {}

    public NotificationSequenceStep(String trigger, String eventType) {
        this.trigger = trigger;
        this.eventType = eventType;
    }

    public String getTrigger() { return trigger; }
    public Long getOffsetMs() { return offsetMs; }
    public String getEventType() { return eventType; }

    public NotificationSequenceStep offsetMs(long offsetMs) { this.offsetMs = offsetMs; return this; }

    /** Serialise for the wire, omitting the offset when it was never set. */
    public String toJson() {
        return Json.write(this);
    }
}
