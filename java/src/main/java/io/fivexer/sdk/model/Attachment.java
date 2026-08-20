package io.fivexer.sdk.model;

/**
 * An attachment record. {@code status} is {@code pending} until the upload is confirmed.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class Attachment {
    private String id;
    private String taskId;
    private String filename;
    private String contentType;
    private long sizeBytes;
    private String status;
    private AttachmentUploader uploader;
    private long createdAt;
    private Long confirmedAt;

    public String getId() { return id; }
    public String getTaskId() { return taskId; }
    public String getFilename() { return filename; }
    public String getContentType() { return contentType; }
    public long getSizeBytes() { return sizeBytes; }
    public String getStatus() { return status; }
    public AttachmentUploader getUploader() { return uploader; }
    public long getCreatedAt() { return createdAt; }
    public Long getConfirmedAt() { return confirmedAt; }
}
