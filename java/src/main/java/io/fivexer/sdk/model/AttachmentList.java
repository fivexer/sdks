package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for {@code GET /v1/tasks/{id}/attachments}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AttachmentList {
    private List<Attachment> attachments;

    public List<Attachment> getAttachments() { return attachments; }
}
