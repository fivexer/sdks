package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for {@code GET /v1/notification-channels}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class NotificationChannelList {
    private List<NotificationChannel> channels;

    public List<NotificationChannel> getChannels() { return channels; }
}
