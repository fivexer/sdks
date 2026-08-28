package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.Attachment;
import io.fivexer.sdk.model.AttachmentDownload;
import io.fivexer.sdk.model.CreatedAttachment;
import io.fivexer.sdk.model.VoiceIceServer;
import io.fivexer.sdk.model.VoiceIceServers;
import io.fivexer.sdk.model.WorkerCreateAttachment;
import java.nio.charset.StandardCharsets;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Worker-plane attachments and voice ICE.
 *
 * <p>Attachments are how an unattended agent hands over a deliverable as a file rather than a
 * chunked comment thread. The storage core is the workspace plane's — presigned PUT, then a
 * confirm that makes the bytes readable — with two deliberate differences: the uploader comes
 * from the session, so there is no {@code workerId} input to spoof, and there is no
 * {@code remove}, because a worker who could delete files could erase the evidence of their own
 * work.
 *
 * <p>Voice is <strong>experimental</strong> and not production-ready; the tests below pin only
 * the path and the {@code voice_disabled} behaviour so that surface cannot drift silently while
 * it settles.
 */
class WorkerAttachmentsAndVoiceTest extends MockServerBase {

    private static final String ATTACHMENT_JSON = "{\"id\":\"att_1\",\"taskId\":\"task_1\","
            + "\"filename\":\"report.pdf\",\"contentType\":\"application/pdf\",\"sizeBytes\":11,"
            + "\"status\":\"pending\",\"uploader\":{\"type\":\"worker\",\"id\":\"agent_1\"},"
            + "\"createdAt\":1756000000000}";

    private FivexerSupervisor supervisor() {
        return new FivexerSupervisor(server.url("/").toString(), "sv_s3ss10n", null, 0);
    }

    // ---- attachments ----

    @Test
    void creating_posts_to_the_portal_path_with_the_session_token() throws Exception {
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"https://s/x\","
                + "\"method\":\"PUT\",\"headers\":{},\"expiresAt\":1}}");

        CreatedAttachment created = worker().attachments().create("task_1",
                new WorkerCreateAttachment("report.pdf", "application/pdf", 11));

        RecordedRequest request = takeRequest();
        assertEquals("POST", request.getMethod());
        assertEquals("/v1/portal/tasks/task_1/attachments", request.getPath());
        assertEquals("Bearer wt_s3ss10n", request.getHeader("Authorization"));
        assertEquals("att_1", created.getAttachment().getId());
    }

    @Test
    void the_create_body_carries_no_worker_id() throws Exception {
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"https://s/x\","
                + "\"method\":\"PUT\",\"headers\":{},\"expiresAt\":1}}");

        worker().attachments().create("task_1",
                new WorkerCreateAttachment("report.pdf", "application/pdf", 11));

        // On this plane the uploader is the session. A workerId field here would be an
        // invitation to attribute a file to someone else, which the separate input type prevents.
        JsonObject body = bodyOf(takeRequest());
        assertEquals(3, body.size());
        assertEquals("report.pdf", body.get("filename").getAsString());
        assertFalse(body.has("workerId"));
    }

    @Test
    void confirm_list_and_download_use_the_portal_paths() throws Exception {
        enqueueJson(200, "{\"attachment\":{\"id\":\"att_1\",\"status\":\"ready\"}}");
        Attachment confirmed = worker().attachments().confirm("task_1", "att_1");
        assertEquals("/v1/portal/tasks/task_1/attachments/att_1/confirm", takeRequest().getPath());
        assertEquals("ready", confirmed.getStatus());

        enqueueJson(200, "{\"attachments\":[" + ATTACHMENT_JSON + "]}");
        List<Attachment> listed = worker().attachments().list("task_1");
        RecordedRequest listRequest = takeRequest();
        assertEquals("GET", listRequest.getMethod());
        assertEquals("/v1/portal/tasks/task_1/attachments", listRequest.getPath());
        assertEquals(1, listed.size());

        enqueueJson(200, "{\"url\":\"https://storage.test/get\",\"expiresAt\":1}");
        AttachmentDownload download = worker().attachments().download("task_1", "att_1");
        assertEquals("/v1/portal/tasks/task_1/attachments/att_1/download", takeRequest().getPath());
        assertEquals("https://storage.test/get", download.getUrl());
    }

    @Test
    void an_empty_attachment_list_reads_as_an_empty_list_not_a_null() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(worker().attachments().list("task_1").isEmpty());
    }

    @Test
    void ids_with_slashes_cannot_escape_the_portal_path() throws Exception {
        enqueueJson(200, "{\"attachments\":[]}");

        worker().attachments().list("tenant/task");

        assertEquals("/v1/portal/tasks/tenant%2Ftask/attachments", takeRequest().getPath());
    }

    /** Points the presigned URL back at the mock server so all three hops are observable. */
    private void enqueueUploadFlow(int storageStatus) {
        String uploadUrl = server.url("/storage/att_1").toString();
        enqueueJson(201, "{\"attachment\":" + ATTACHMENT_JSON + ",\"upload\":{\"url\":\"" + uploadUrl
                + "\",\"method\":\"PUT\",\"headers\":{\"content-type\":\"application/pdf\","
                + "\"x-amz-meta-task\":\"task_1\"},\"expiresAt\":1}}");
        enqueueEmpty(storageStatus);
        enqueueJson(200, "{\"attachment\":{\"id\":\"att_1\",\"status\":\"ready\"}}");
    }

    @Test
    void uploading_creates_puts_the_bytes_then_confirms() throws Exception {
        enqueueUploadFlow(200);

        Attachment attachment = worker().attachments()
                .upload("task_1", "hello world".getBytes(StandardCharsets.UTF_8), "report.pdf",
                        "application/pdf");

        assertEquals("ready", attachment.getStatus());
        RecordedRequest create = takeRequest();
        assertEquals("/v1/portal/tasks/task_1/attachments", create.getPath());
        // The size is derived from the payload, never trusted from the caller — the server HEADs
        // the object on confirm, so a wrong number here would fail late instead of at create.
        assertEquals(11, bodyOf(create).get("sizeBytes").getAsInt());

        RecordedRequest storagePut = takeRequest();
        assertEquals("PUT", storagePut.getMethod());
        assertEquals("/storage/att_1", storagePut.getPath());
        // The presigned headers are part of the signature; dropping one invalidates it.
        assertEquals("task_1", storagePut.getHeader("x-amz-meta-task"));
        assertEquals("hello world", storagePut.getBody().readUtf8());
        // The session token must not reach a third-party storage host.
        assertNull(storagePut.getHeader("Authorization"));

        assertEquals("/v1/portal/tasks/task_1/attachments/att_1/confirm", takeRequest().getPath());
    }

    @Test
    void a_storage_rejection_surfaces_as_an_upload_failure_and_skips_confirmation() throws Exception {
        enqueueUploadFlow(403);

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> worker().attachments().upload("task_1",
                        "hello world".getBytes(StandardCharsets.UTF_8), "report.pdf", "application/pdf"));

        // A storage rejection is not a /v1 error; one code keeps it diagnosable, and the record
        // stays unconfirmed rather than claiming bytes that never landed.
        assertEquals("upload_failed", error.getCode());
        assertEquals(403, error.getStatusCode());
    }

    @Test
    void the_worker_plane_has_no_attachment_remove() {
        // Files on a task are an operator's to manage and a worker's only to add and read. The
        // absence is the contract, so it is asserted rather than left to be noticed.
        for (java.lang.reflect.Method method : WorkerAttachments.class.getMethods()) {
            assertFalse(method.getName().equals("remove"),
                    "the worker plane must not expose an attachment remove");
        }
    }

    // ---- voice ICE (experimental) ----

    private static final String ICE = "{\"iceServers\":["
            + "{\"urls\":\"stun:stun.test:3478\"},"
            + "{\"urls\":[\"turn:turn.test:3478\",\"turns:turn.test:5349\"],"
            + "\"username\":\"agent_1\",\"credential\":\"s3cret\"}]}";

    @Test
    void the_worker_plane_reads_ice_from_the_portal_path() throws Exception {
        enqueueJson(200, ICE);

        VoiceIceServers ice = worker().voiceIce();

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/portal/voice/ice", request.getPath());
        assertEquals(2, ice.getIceServers().size());
    }

    @Test
    void a_single_url_and_an_array_of_urls_both_read_as_a_list() throws Exception {
        enqueueJson(200, ICE);

        List<VoiceIceServer> servers = worker().voiceIce().getIceServers();

        // The wire sends either form; normalising to a list means a caller iterating does not
        // have to branch on which one arrived.
        assertEquals(List.of("stun:stun.test:3478"), servers.get(0).getUrls());
        assertEquals(List.of("turn:turn.test:3478", "turns:turn.test:5349"), servers.get(1).getUrls());
    }

    @Test
    void the_session_plane_receives_the_turn_credential() throws Exception {
        enqueueJson(200, ICE);

        VoiceIceServer relay = worker().voiceIce().getIceServers().get(1);

        // The console's read view reports credentialSet and withholds the value; a session plane
        // must hand over the real secret or the browser cannot authenticate to the relay.
        assertEquals("s3cret", relay.getCredential());
        assertEquals("agent_1", relay.getUsername());
    }

    @Test
    void voice_disabled_raises_rather_than_reading_as_no_relays() throws Exception {
        enqueueJson(404, "{\"error\":{\"code\":\"voice_disabled\",\"message\":\"voice is off\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> worker().voiceIce());

        // An empty list would read as "no relay configured, go direct" — a different, and
        // silently broken, outcome from "this workspace has no voice".
        assertEquals(404, error.getStatusCode());
        assertEquals("voice_disabled", error.getCode());
    }

    @Test
    void the_supervisor_plane_reads_its_own_ice_path() throws Exception {
        enqueueJson(200, ICE);

        VoiceIceServers ice = supervisor().voiceIce();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor/voice/ice", request.getPath());
        assertEquals("Bearer sv_s3ss10n", request.getHeader("Authorization"));
        assertEquals(2, ice.getIceServers().size());
    }

    @Test
    void a_relay_that_arrives_without_urls_reads_as_no_urls_rather_than_throwing() throws Exception {
        enqueueJson(200, "{\"iceServers\":[{\"username\":\"agent_1\"},{\"urls\":null}]}");

        List<VoiceIceServer> servers = worker().voiceIce().getIceServers();

        // Voice is experimental and the server's shape may still move. A malformed relay entry
        // must not take down the call setup for the well-formed ones beside it.
        assertTrue(servers.get(0).getUrls().isEmpty());
        assertTrue(servers.get(1).getUrls().isEmpty());
    }

    @Test
    void an_empty_ice_response_reads_as_an_empty_list() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(worker().voiceIce().getIceServers().isEmpty());
    }
}
