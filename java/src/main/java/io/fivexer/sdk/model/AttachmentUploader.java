package io.fivexer.sdk.model;

/**
 * Who uploaded an attachment.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AttachmentUploader {
    private String type;
    private String id;

    public String getType() { return type; }
    public String getId() { return id; }
}
