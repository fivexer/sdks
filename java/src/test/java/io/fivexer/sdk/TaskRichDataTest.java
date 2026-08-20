package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.AddComment;
import io.fivexer.sdk.model.Attachment;
import io.fivexer.sdk.model.AttachmentDownload;
import io.fivexer.sdk.model.Comment;
import io.fivexer.sdk.model.CommentPage;
import io.fivexer.sdk.model.CreateAttachment;
import io.fivexer.sdk.model.CreatedAttachment;
import io.fivexer.sdk.model.SetTaskContext;
import io.fivexer.sdk.model.TaskContext;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/** Task context, comments and attachments — the rich-data surface around a task. */
class TaskRichDataTest extends MockServerBase {

    private static final String CONTEXT_JSON = "{\"taskId\":\"task_8fk2\",\"title\":\"Refund request\","
            + "\"description\":\"Customer wants a refund for order 41\",\"context\":{\"orderId\":\"41\"},"
            + "\"references\":[{\"id\":\"ref_1\",\"url\":\"https://crm/o/41\",\"label\":\"Order 41\","
            + "\"contentType\":\"text/html\"}],\"createdAt\":1750000000000,\"updatedAt\":1750000900000}";

    private static final String ATTACHMENT_JSON = "{\"id\":\"att_1\",\"taskId\":\"task_8fk2\","
            + "\"filename\":\"receipt.pdf\",\"contentType\":\"application/pdf\",\"sizeBytes\":6,"
            + "\"status\":\"pending\",\"uploader\":{\"type\":\"api\",\"id\":null},"
            + "\"createdAt\":1750001000000,\"confirmedAt\":null}";

    // ---- context ----

    @Test
    void reading_the_context_of_a_task_returns_its_references() throws Exception {
        enqueueJson(200, CONTEXT_JSON);

        TaskContext context = client().tasks().context().get("task_8fk2");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/context", request.getPath());
        assertEquals("Refund request", context.getTitle());
        assertEquals("Customer wants a refund for order 41", context.getDescription());
        assertEquals("41", context.getContext().get("orderId"));
        assertEquals("task_8fk2", context.getTaskId());
        assertEquals("https://crm/o/41", context.getReferences().get(0).getUrl());
        assertEquals("Order 41", context.getReferences().get(0).getLabel());
        assertEquals("text/html", context.getReferences().get(0).getContentType());
        assertEquals("ref_1", context.getReferences().get(0).getId());
        assertEquals(1750000000000L, context.getCreatedAt());
        assertEquals(1750000900000L, context.getUpdatedAt());
    }

    @Test
    void setting_context_replaces_it_wholesale() throws Exception {
        enqueueJson(200, CONTEXT_JSON);

        client().tasks().context().set("task_8fk2", new SetTaskContext().title("Refund request"));

        RecordedRequest request = takeRequest();
        assertEquals("PUT", request.getMethod());
        assertEquals("Refund request", bodyOf(request).get("title").getAsString());
    }

    @Test
    void clearing_context_sends_a_delete_and_returns_nothing() throws Exception {
        enqueueEmpty(204);

        client().tasks().context().clear("task_8fk2");

        assertEquals("DELETE", takeRequest().getMethod());
    }

    // ---- comments ----

    @Test
    void adding_a_comment_returns_the_stored_comment_not_the_envelope() throws Exception {
        enqueueJson(201, "{\"comment\":{\"id\":\"cmt_1\",\"taskId\":\"task_8fk2\","
                + "\"author\":{\"type\":\"worker\",\"id\":\"agent_1\",\"label\":\"Ada\"},"
                + "\"body\":\"Called the customer back\",\"createdAt\":1750001000000}}");

        Comment comment = client().tasks().comments()
                .add("task_8fk2", new AddComment("Called the customer back").workerId("agent_1"));

        assertEquals("cmt_1", comment.getId());
        assertEquals("task_8fk2", comment.getTaskId());
        assertEquals("Called the customer back", comment.getBody());
        assertEquals(1750001000000L, comment.getCreatedAt());
        assertEquals("worker", comment.getAuthor().getType());
        assertEquals("agent_1", comment.getAuthor().getId());
        assertEquals("Ada", comment.getAuthor().getLabel());
    }

    @Test
    void listing_comments_reports_the_cursor_for_the_next_page() throws Exception {
        enqueueJson(200, "{\"comments\":[{\"id\":\"cmt_1\",\"taskId\":\"task_8fk2\","
                + "\"author\":{\"type\":\"api\",\"id\":null,\"label\":null},\"body\":\"Escalated\","
                + "\"createdAt\":1}],\"nextCursor\":\"cursor_c1\",\"hasMore\":true}");

        CommentPage page = client().tasks().comments().list("task_8fk2", null, 1);

        RecordedRequest request = takeRequest();
        assertEquals("limit=1", queryOf(request));
        assertTrue(page.isHasMore());
        assertEquals("cursor_c1", page.getNextCursor());
        assertEquals("Escalated", page.getComments().get(0).getBody());
        assertNull(page.getComments().get(0).getAuthor().getId());
    }

    @Test
    void listing_comments_without_paging_sends_no_query() throws Exception {
        enqueueJson(200, "{\"comments\":[],\"nextCursor\":null,\"hasMore\":false}");

        client().tasks().comments().list("task_8fk2");

        assertEquals("/v1/tasks/task_8fk2/comments", takeRequest().getPath());
    }

    @Test
    void removing_a_comment_targets_it_by_id() throws Exception {
        enqueueEmpty(204);

        client().tasks().comments().remove("task_8fk2", "cmt_1");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/tasks/task_8fk2/comments/cmt_1", request.getPath());
    }

    // ---- attachments ----

    @Test
    void creating_an_attachment_returns_where_to_put_the_bytes() throws Exception {
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{"
                + "\"url\":\"https://storage.test/att_1?sig=abc\",\"method\":\"PUT\","
                + "\"headers\":{\"content-type\":\"application/pdf\"},\"expiresAt\":1750004600000}}");

        CreatedAttachment created = client().tasks().attachments()
                .create("task_8fk2", new CreateAttachment("receipt.pdf", "application/pdf", 6L));

        assertEquals("att_1", created.getAttachment().getId());
        assertEquals("task_8fk2", created.getAttachment().getTaskId());
        assertEquals("receipt.pdf", created.getAttachment().getFilename());
        assertEquals("application/pdf", created.getAttachment().getContentType());
        assertEquals(6L, created.getAttachment().getSizeBytes());
        assertEquals("pending", created.getAttachment().getStatus());
        assertEquals(1750001000000L, created.getAttachment().getCreatedAt());
        assertNull(created.getAttachment().getConfirmedAt());
        assertEquals("api", created.getAttachment().getUploader().getType());
        assertNull(created.getAttachment().getUploader().getId());
        assertEquals("https://storage.test/att_1?sig=abc", created.getUpload().getUrl());
        assertEquals("PUT", created.getUpload().getMethod());
        assertEquals("application/pdf", created.getUpload().getHeaders().get("content-type"));
        assertEquals(1750004600000L, created.getUpload().getExpiresAt());
    }

    @Test
    void confirming_an_attachment_marks_it_ready() throws Exception {
        enqueueJson(200, "{\"attachment\":{\"id\":\"att_1\",\"status\":\"ready\",\"confirmedAt\":2}}");

        Attachment attachment = client().tasks().attachments().confirm("task_8fk2", "att_1");

        assertEquals("ready", attachment.getStatus());
        assertEquals(2L, attachment.getConfirmedAt());
        assertEquals("/v1/tasks/task_8fk2/attachments/att_1/confirm", takeRequest().getPath());
    }

    @Test
    void listing_attachments_unwraps_the_envelope() throws Exception {
        enqueueJson(200, "{\"attachments\":[" + ATTACHMENT_JSON + "]}");

        List<Attachment> attachments = client().tasks().attachments().list("task_8fk2");

        assertEquals(1, attachments.size());
        assertEquals("att_1", attachments.get(0).getId());
    }

    @Test
    void listing_attachments_of_a_task_with_none_returns_an_empty_list() throws Exception {
        // A null array in the envelope must not surface as a null list to the caller.
        enqueueJson(200, "{}");

        assertTrue(client().tasks().attachments().list("task_8fk2").isEmpty());
    }

    @Test
    void downloading_an_attachment_returns_a_short_lived_url() throws Exception {
        enqueueJson(200, "{\"url\":\"https://storage.test/dl\",\"expiresAt\":1750004600000}");

        AttachmentDownload download = client().tasks().attachments().download("task_8fk2", "att_1");

        assertEquals("https://storage.test/dl", download.getUrl());
        assertEquals(1750004600000L, download.getExpiresAt());
        assertEquals("/v1/tasks/task_8fk2/attachments/att_1/download", takeRequest().getPath());
    }

    @Test
    void removing_an_attachment_targets_it_by_id() throws Exception {
        enqueueEmpty(204);

        client().tasks().attachments().remove("task_8fk2", "att_1");

        assertEquals("/v1/tasks/task_8fk2/attachments/att_1", takeRequest().getPath());
    }

    // ---- the upload helper: create -> PUT to storage -> confirm ----

    /** Points the presigned URL back at the mock server so all three hops are observable. */
    private void enqueueUploadFlow(int storageStatus) {
        String uploadUrl = server.url("/storage/att_1").toString();
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"" + uploadUrl
                + "\",\"method\":\"PUT\",\"headers\":{\"content-type\":\"application/pdf\","
                + "\"x-amz-meta-task\":\"task_8fk2\"},\"expiresAt\":1}}");
        enqueueEmpty(storageStatus);
        enqueueJson(200, "{\"attachment\":{\"id\":\"att_1\",\"status\":\"ready\"}}");
    }

    @Test
    void uploading_a_file_reserves_stores_and_confirms_it_in_one_call() throws Exception {
        enqueueUploadFlow(200);

        Attachment attachment = client().tasks().attachments()
                .upload("task_8fk2", "hello!".getBytes(java.nio.charset.StandardCharsets.UTF_8),
                        "receipt.pdf", "application/pdf");

        assertEquals("ready", attachment.getStatus());
        assertEquals("/v1/tasks/task_8fk2/attachments", takeRequest().getPath());
        assertEquals("/storage/att_1", takeRequest().getPath());
        assertEquals("/v1/tasks/task_8fk2/attachments/att_1/confirm", takeRequest().getPath());
    }

    @Test
    void uploading_derives_the_size_from_the_payload() throws Exception {
        enqueueUploadFlow(200);

        client().tasks().attachments().upload("task_8fk2",
                "hello!".getBytes(java.nio.charset.StandardCharsets.UTF_8), "receipt.pdf", "application/pdf");

        assertEquals(6, bodyOf(takeRequest()).get("sizeBytes").getAsInt());
    }

    @Test
    void uploading_sends_the_presigned_headers_verbatim_and_no_api_key() throws Exception {
        // The headers are part of the signature; the API key has no business at object storage.
        enqueueUploadFlow(200);

        client().tasks().attachments().upload("task_8fk2",
                "hello!".getBytes(java.nio.charset.StandardCharsets.UTF_8), "receipt.pdf", "application/pdf");

        takeRequest();
        RecordedRequest storagePut = takeRequest();
        assertEquals("PUT", storagePut.getMethod());
        assertEquals("task_8fk2", storagePut.getHeader("x-amz-meta-task"));
        assertEquals("hello!", storagePut.getBody().readUtf8());
        assertNull(storagePut.getHeader("Authorization"));
    }

    @Test
    void a_storage_rejection_surfaces_as_an_upload_failure_and_skips_confirmation() throws Exception {
        enqueueUploadFlow(403);

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> client().tasks()
                .attachments().upload("task_8fk2",
                        "hello!".getBytes(java.nio.charset.StandardCharsets.UTF_8),
                        "receipt.pdf", "application/pdf"));

        assertEquals("upload_failed", error.getCode());
        assertEquals(403, error.getStatusCode());
        // Only create and the storage PUT were attempted — the record stays unconfirmed rather
        // than claiming bytes that never landed.
        assertEquals("/v1/tasks/task_8fk2/attachments", takeRequest().getPath());
        assertEquals("/storage/att_1", takeRequest().getPath());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void uploading_can_attribute_the_file_to_a_worker() throws Exception {
        enqueueUploadFlow(200);

        client().tasks().attachments().upload("task_8fk2",
                "hello!".getBytes(java.nio.charset.StandardCharsets.UTF_8),
                "receipt.pdf", "application/pdf", "agent_1");

        assertEquals("agent_1", bodyOf(takeRequest()).get("workerId").getAsString());
    }

    @Test
    void an_upload_target_without_an_explicit_method_defaults_to_put() throws Exception {
        String uploadUrl = server.url("/storage/att_1").toString();
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"" + uploadUrl
                + "\",\"expiresAt\":1}}");
        enqueueEmpty(200);
        enqueueJson(200, "{\"attachment\":{\"id\":\"att_1\",\"status\":\"ready\"}}");

        client().tasks().attachments().upload("task_8fk2", new byte[] {1},
                "receipt.pdf", "application/pdf");

        takeRequest();
        assertEquals("PUT", takeRequest().getMethod());
    }

    @Test
    void the_created_attachment_reports_its_task_and_uploader() throws Exception {
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"u\",\"expiresAt\":1}}");

        CreatedAttachment created = client().tasks().attachments()
                .create("task_8fk2", new CreateAttachment("receipt.pdf", "application/pdf", 6L)
                        .workerId("agent_1"));

        assertNotNull(created.getAttachment());
        assertEquals("task_8fk2", created.getAttachment().getTaskId());
        assertEquals("agent_1", bodyOf(takeRequest()).get("workerId").getAsString());
    }
}
