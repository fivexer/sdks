package io.fivexer.sdk.model;

/**
 * Envelope for the single-attachment responses.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AttachmentEnvelope {
    private Attachment attachment;

    public Attachment getAttachment() { return attachment; }
}
