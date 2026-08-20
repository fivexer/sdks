package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.Attachment;
import io.fivexer.sdk.model.AttachmentDownload;
import io.fivexer.sdk.model.AttachmentEnvelope;
import io.fivexer.sdk.model.AttachmentList;
import io.fivexer.sdk.model.CreateAttachment;
import io.fivexer.sdk.model.CreatedAttachment;
import java.util.Collections;
import java.util.List;

/**
 * Files attached to a task.
 *
 * <p>Uploading is a three-step dance — reserve a record, PUT the bytes straight to object
 * storage with the presigned headers, then confirm — which {@link #upload} performs for you.
 */
public final class TaskAttachments {

    private final Fivexer client;

    TaskAttachments(Fivexer client) {
        this.client = client;
    }

    /** Reserve an attachment record and get a presigned URL to PUT the bytes to. */
    public CreatedAttachment create(String taskId, CreateAttachment input) {
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/attachments", input.toJson(),
                null, CreatedAttachment.class, false);
    }

    /** Mark an attachment ready once its bytes have landed in object storage. */
    public Attachment confirm(String taskId, String attachmentId) {
        AttachmentEnvelope envelope = client.request("POST",
                "/tasks/" + Json.enc(taskId) + "/attachments/" + Json.enc(attachmentId) + "/confirm",
                null, null, AttachmentEnvelope.class, false);
        return envelope.getAttachment();
    }

    public List<Attachment> list(String taskId) {
        AttachmentList envelope = client.request("GET", "/tasks/" + Json.enc(taskId) + "/attachments",
                null, null, AttachmentList.class, false);
        return envelope.getAttachments() == null ? Collections.emptyList() : envelope.getAttachments();
    }

    public AttachmentDownload download(String taskId, String attachmentId) {
        return client.request("GET",
                "/tasks/" + Json.enc(taskId) + "/attachments/" + Json.enc(attachmentId) + "/download",
                null, null, AttachmentDownload.class, false);
    }

    public void remove(String taskId, String attachmentId) {
        client.request("DELETE", "/tasks/" + Json.enc(taskId) + "/attachments/" + Json.enc(attachmentId),
                null, null, Void.class, true);
    }

    /**
     * Create, PUT the bytes to object storage, then confirm — in one call.
     *
     * <p>The size is derived from {@code payload}, and the presigned headers are sent verbatim
     * because they are part of the signature. A non-2xx from storage throws a
     * {@link FivexerApiException} with code {@code upload_failed}, leaving the record
     * unconfirmed rather than claiming bytes that never landed.
     */
    public Attachment upload(String taskId, byte[] payload, String filename, String contentType) {
        return upload(taskId, payload, filename, contentType, null);
    }

    /** @param workerId attribute the upload to a worker (API-key callers only) */
    public Attachment upload(String taskId, byte[] payload, String filename, String contentType,
            String workerId) {
        CreatedAttachment created = create(taskId,
                new CreateAttachment(filename, contentType, (long) payload.length).workerId(workerId));
        client.putBytes(created.getUpload(), payload);
        return confirm(taskId, created.getAttachment().getId());
    }
}
