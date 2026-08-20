package io.fivexer.sdk;

/** Notification sequences (when to notify) and channels (where to deliver). */
public final class Notifications {

    private final NotificationSequences sequencesResource;
    private final NotificationChannels channelsResource;

    Notifications(Fivexer client) {
        this.sequencesResource = new NotificationSequences(client);
        this.channelsResource = new NotificationChannels(client);
    }

    public NotificationSequences sequences() { return sequencesResource; }

    public NotificationChannels channels() { return channelsResource; }
}
