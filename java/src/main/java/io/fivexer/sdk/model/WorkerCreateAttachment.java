package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Worker-plane upload input.
 *
 * <p>The same shape as {@link CreateAttachment} minus {@code workerId}: on this plane the
 * uploader is derived from the session, so a worker cannot attribute a file to anyone else.
 */
public class WorkerCreateAttachment {

    private final String filename;
    private final String contentType;
    private final long sizeBytes;

    public WorkerCreateAttachment(String filename, String contentType, long sizeBytes) {
        this.filename = filename;
        this.contentType = contentType;
        this.sizeBytes = sizeBytes;
    }

    public String getFilename() { return filename; }
    public String getContentType() { return contentType; }
    public long getSizeBytes() { return sizeBytes; }

    public String toJson() {
        return Json.write(this);
    }
}
