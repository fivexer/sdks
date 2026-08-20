package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for reserving an attachment record.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class CreateAttachment {
    private String filename;
    private String contentType;
    private Long sizeBytes;
    private String workerId;  // attribute the upload to a worker (API-key callers only)

    public CreateAttachment(String filename, String contentType, Long sizeBytes) {
        if (filename == null) {
            throw new IllegalArgumentException("filename is required");
        }
        if (contentType == null) {
            throw new IllegalArgumentException("contentType is required");
        }
        if (sizeBytes == null) {
            throw new IllegalArgumentException("sizeBytes is required");
        }
        this.filename = filename;
        this.contentType = contentType;
        this.sizeBytes = sizeBytes;
    }

    public String getFilename() { return filename; }
    public String getContentType() { return contentType; }
    public Long getSizeBytes() { return sizeBytes; }
    public String getWorkerId() { return workerId; }

    public CreateAttachment workerId(String workerId) { this.workerId = workerId; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
