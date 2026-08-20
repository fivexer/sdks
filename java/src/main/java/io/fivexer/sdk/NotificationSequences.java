package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.CreateNotificationSequence;
import io.fivexer.sdk.model.NotificationSequence;
import io.fivexer.sdk.model.NotificationSequenceList;
import io.fivexer.sdk.model.UpdateNotificationSequence;
import java.util.Collections;
import java.util.List;

/** Sequences describe when to notify, relative to a task lifecycle trigger. */
public final class NotificationSequences {

    private final Fivexer client;

    NotificationSequences(Fivexer client) {
        this.client = client;
    }

    public List<NotificationSequence> list() {
        NotificationSequenceList envelope = client.request("GET", "/notification-sequences", null, null,
                NotificationSequenceList.class, false);
        return envelope.getSequences() == null ? Collections.emptyList() : envelope.getSequences();
    }

    public NotificationSequence create(CreateNotificationSequence input) {
        return client.request("POST", "/notification-sequences", input.toJson(), null,
                NotificationSequence.class, false);
    }

    public NotificationSequence get(String sequenceId) {
        return client.request("GET", "/notification-sequences/" + Json.enc(sequenceId), null, null,
                NotificationSequence.class, false);
    }

    /** Partial update — fields left unset keep their stored value. */
    public NotificationSequence update(String sequenceId, UpdateNotificationSequence update) {
        return client.request("PATCH", "/notification-sequences/" + Json.enc(sequenceId), update.toJson(),
                null, NotificationSequence.class, false);
    }

    public void remove(String sequenceId) {
        client.request("DELETE", "/notification-sequences/" + Json.enc(sequenceId), null, null,
                Void.class, true);
    }
}
