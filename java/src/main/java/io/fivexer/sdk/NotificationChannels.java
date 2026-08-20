package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.CreateNotificationChannel;
import io.fivexer.sdk.model.NotificationChannel;
import io.fivexer.sdk.model.NotificationChannelList;
import io.fivexer.sdk.model.UpdateNotificationChannel;
import java.util.Collections;
import java.util.List;

/** Channels describe where to deliver. The signing secret is write-only and never returned. */
public final class NotificationChannels {

    private final Fivexer client;

    NotificationChannels(Fivexer client) {
        this.client = client;
    }

    public List<NotificationChannel> list() {
        NotificationChannelList envelope = client.request("GET", "/notification-channels", null, null,
                NotificationChannelList.class, false);
        return envelope.getChannels() == null ? Collections.emptyList() : envelope.getChannels();
    }

    public NotificationChannel create(CreateNotificationChannel input) {
        return client.request("POST", "/notification-channels", input.toJson(), null,
                NotificationChannel.class, false);
    }

    public NotificationChannel get(String channelId) {
        return client.request("GET", "/notification-channels/" + Json.enc(channelId), null, null,
                NotificationChannel.class, false);
    }

    /** Partial update — fields left unset keep their stored value. */
    public NotificationChannel update(String channelId, UpdateNotificationChannel update) {
        return client.request("PATCH", "/notification-channels/" + Json.enc(channelId), update.toJson(),
                null, NotificationChannel.class, false);
    }

    public void remove(String channelId) {
        client.request("DELETE", "/notification-channels/" + Json.enc(channelId), null, null,
                Void.class, true);
    }
}
