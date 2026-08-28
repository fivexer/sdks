package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.Attachment;
import io.fivexer.sdk.model.AttachmentDownload;
import io.fivexer.sdk.model.AttachmentEnvelope;
import io.fivexer.sdk.model.AttachmentList;
import io.fivexer.sdk.model.CreatedAttachment;
import io.fivexer.sdk.model.WorkerCreateAttachment;
import java.util.Collections;
import java.util.List;

/**
 * Files on the worker's own tasks — how an unattended agent hands over a deliverable as a file
 * instead of a chunked comment thread.
 *
 * <p>The same storage core as {@link TaskAttachments}: bytes go straight to object storage via a
 * presigned PUT, and {@link #confirm} is what makes them readable. Two differences, both
 * deliberate. The uploader is derived from the session, so {@link WorkerCreateAttachment} has no
 * {@code workerId} to spoof. And there is no {@code remove} — files on a task are an operator's
 * to manage and a worker's only to add and read; a delete here would let a worker erase the
 * evidence of their own work.
 *
 * <p>Deployments without object storage answer 501 {@code storage_unavailable}.
 */
public final class WorkerAttachments {

    private final FivexerWorker client;

    WorkerAttachments(FivexerWorker client) {
        this.client = client;
    }

    /** Reserve an attachment record and get a presigned URL to PUT the bytes to. */
    public CreatedAttachment create(String taskId, WorkerCreateAttachment input) {
        return client.request("POST", "/portal/tasks/" + Json.enc(taskId) + "/attachments",
                input.toJson(), CreatedAttachment.class, false);
    }

    /** Confirm the bytes landed — the server HEADs the object as the authoritative size check. */
    public Attachment confirm(String taskId, String attachmentId) {
        AttachmentEnvelope envelope = client.request("POST",
                "/portal/tasks/" + Json.enc(taskId) + "/attachments/" + Json.enc(attachmentId) + "/confirm",
                null, AttachmentEnvelope.class, false);
        return envelope.getAttachment();
    }

    public List<Attachment> list(String taskId) {
        AttachmentList envelope = client.request("GET",
                "/portal/tasks/" + Json.enc(taskId) + "/attachments", null, AttachmentList.class, false);
        return envelope.getAttachments() == null ? Collections.emptyList() : envelope.getAttachments();
    }

    /** A short-lived presigned download URL for a confirmed attachment. */
    public AttachmentDownload download(String taskId, String attachmentId) {
        return client.request("GET",
                "/portal/tasks/" + Json.enc(taskId) + "/attachments/" + Json.enc(attachmentId) + "/download",
                null, AttachmentDownload.class, false);
    }

    /**
     * Create, PUT the bytes to object storage, then confirm — in one call.
     *
     * <p>The size is derived from {@code payload}, and the presigned headers are sent verbatim
     * because they are part of the signature. A non-2xx from storage throws a
     * {@link FivexerApiException} with code {@code upload_failed}, leaving the record unconfirmed
     * rather than claiming bytes that never landed.
     */
    public Attachment upload(String taskId, byte[] payload, String filename, String contentType) {
        CreatedAttachment created = create(taskId,
                new WorkerCreateAttachment(filename, contentType, (long) payload.length));
        client.putBytes(created.getUpload(), payload);
        return confirm(taskId, created.getAttachment().getId());
    }
}
