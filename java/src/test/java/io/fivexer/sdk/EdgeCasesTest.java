package io.fivexer.sdk;

import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.webhook.Webhook;
import io.fivexer.sdk.webhook.WebhookEvent;
import org.junit.jupiter.api.Test;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Edge cases that exercise the remaining contract branches: constructor null validation,
 * transport failure handling, error envelopes without an error object, nullable resource
 * inputs, and webhook header/payload variants.
 */
class EdgeCasesTest extends MockServerBase {

    @Test
    void constructor_rejects_null_base_url_and_null_api_key() {
        assertThrows(IllegalArgumentException.class, () -> new Fivexer(null, "sk_x"));
        assertThrows(IllegalArgumentException.class, () -> new Fivexer("https://api.fivexer.test", null));
    }

    @Test
    void an_unreachable_host_surfaces_as_a_fivexer_exception() {
        // A no-retry, fast-failing client so connection refusal is deterministic and well
        // under the per-test budget (otherwise OkHttp retries make timing non-deterministic).
        okhttp3.OkHttpClient fastFail = new okhttp3.OkHttpClient.Builder()
                .connectTimeout(15, java.util.concurrent.TimeUnit.MILLISECONDS)
                .retryOnConnectionFailure(false)
                .build();
        Fivexer client = new Fivexer("http://127.0.0.1:1", "sk_test_x", fastFail, 0);
        FivexerException ex = assertThrows(FivexerException.class, client::stats);
        assertNotNull(ex.getMessage());
    }

    @Test
    void an_error_envelope_without_an_error_key_falls_back_to_unknown() {
        enqueueJson(500, "{\"unrelated\":true}");

        FivexerApiException ex = assertThrows(FivexerApiException.class, () -> client().tasks().list(null));
        assertEquals(500, ex.getStatusCode());
        assertEquals("unknown_error", ex.getCode());
    }

    @Test
    void an_error_that_is_not_an_object_falls_back_to_unknown() {
        enqueueJson(409, "{\"error\":\"just a string\"}");

        FivexerApiException ex = assertThrows(FivexerApiException.class, () -> client().tasks().get("t"));
        assertEquals("unknown_error", ex.getCode());
    }

    @Test
    void an_empty_error_object_falls_back_to_unknown_with_default_message() {
        enqueueJson(409, "{\"error\":{}}");

        FivexerApiException ex = assertThrows(FivexerApiException.class, () -> client().tasks().get("t"));
        assertEquals("unknown_error", ex.getCode());
        assertEquals("http 409", ex.getMessage());
    }

    @Test
    void an_error_envelope_with_non_primitive_code_falls_back_to_unknown() {
        enqueueJson(409, "{\"error\":{\"code\":[\"nested\"],\"message\":\"x\"}}");

        FivexerApiException ex = assertThrows(FivexerApiException.class, () -> client().tasks().get("t"));
        assertEquals("unknown_error", ex.getCode());
    }

    @Test
    void upserting_a_null_worker_defaults_to_an_empty_body() {
        enqueueJson(200, "{\"id\":\"w_auto\"}");

        String id = client().workers().upsert(null);

        assertEquals("w_auto", id);
    }

    @Test
    void a_decision_candidate_without_a_worker_id_still_parses() {
        enqueueJson(200, "{\"decisions\":[{\"id\":\"d1\",\"taskId\":\"t1\",\"workerId\":\"a1\","
                + "\"matchedAt\":1,\"mode\":\"first-come\","
                + "\"candidates\":[{\"score\":3}]}]}");

        var decisions = client().decisions().list(null);
        assertEquals(1, decisions.size());
        assertNull(decisions.get(0).getCandidates().get(0).getWorkerId());
        assertEquals(3.0, decisions.get(0).getCandidates().get(0).getDetail().get("score"));
    }

    @Test
    void creating_a_task_with_an_id_sends_it_in_the_body() throws Exception {
        enqueueJson(202, "{\"id\":\"task_9\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("english")).id("task_9"));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"id\":\"task_9\""));
    }

    // ---- webhook edge branches ----

    private static final String SECRET = "whsec_testsecret";
    private static final long NOW = 1750000000000L;

    private static String sign(long ts, byte[] body) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(SECRET.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        mac.update(Long.toString(ts).getBytes(StandardCharsets.US_ASCII));
        mac.update((byte) '.');
        mac.update(body);
        StringBuilder sb = new StringBuilder();
        for (byte b : mac.doFinal()) sb.append(String.format("%02x", b));
        return "t=" + ts + ",v1=" + sb;
    }

    private static final String ZEROS = "0".repeat(64);

    @Test
    void a_null_webhook_header_is_rejected() {
        byte[] raw = "{\"event\":\"task.matched\"}".getBytes(StandardCharsets.UTF_8);
        assertFalse(Webhook.verifySignature(raw, null, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_webhook_with_correct_t_part_but_wrong_v_prefix_is_rejected() {
        byte[] raw = "{}".getBytes(StandardCharsets.UTF_8);
        String header = "t=" + NOW + ",v2=" + ZEROS;
        assertFalse(Webhook.verifySignature(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_webhook_with_an_empty_timestamp_is_rejected() {
        byte[] raw = "{}".getBytes(StandardCharsets.UTF_8);
        String header = "t=,v1=" + ZEROS;
        assertFalse(Webhook.verifySignature(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_non_object_webhook_payload_yields_null_event() throws Exception {
        byte[] raw = "[1,2,3]".getBytes(StandardCharsets.UTF_8);
        String header = sign(NOW, raw);
        WebhookEvent event = Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW);
        assertNull(event.getEvent());
        assertNull(event.getData());
    }

    @Test
    void a_webhook_payload_with_a_non_primitive_event_yields_null_event() throws Exception {
        byte[] raw = "{\"event\":[1,2]}".getBytes(StandardCharsets.UTF_8);
        String header = sign(NOW, raw);
        WebhookEvent event = Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW);
        assertNull(event.getEvent());
        assertNotNull(event.getData());
    }

    @Test
    void retry_with_an_empty_retry_after_string_sleeps_nothing_then_succeeds() throws Exception {
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow down\"}}",
                "retry-after", "");
        enqueueJson(200, "{\"workers\":[],\"count\":0}");

        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", 1);
        assertEquals(0, client.workers().list().getCount());
        assertEquals(2, server.getRequestCount());
    }
}
