package io.fivexer.sdk.model;

/**
 * The attachment record plus where to PUT its bytes.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CreatedAttachment {
    private Attachment attachment;
    private AttachmentUpload upload;

    public Attachment getAttachment() { return attachment; }
    public AttachmentUpload getUpload() { return upload; }
}
