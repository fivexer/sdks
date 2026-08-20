package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for {@code GET /v1/notification-sequences}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class NotificationSequenceList {
    private List<NotificationSequence> sequences;

    public List<NotificationSequence> getSequences() { return sequences; }
}
