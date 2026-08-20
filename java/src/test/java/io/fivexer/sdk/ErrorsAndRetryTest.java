package io.fivexer.sdk;

import io.fivexer.sdk.model.CreateTask;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Error handling, quota-header parsing, transport retry, and constructor validation.
 */
class ErrorsAndRetryTest extends MockServerBase {

    @Test
    void quota_headers_on_a_successful_response_are_exposed_on_the_client() {
        enqueueJson(202, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}",
                "x-quota-task-rate-limit", "300",
                "x-quota-task-rate-remaining", "299",
                "x-quota-queued-tasks-limit", "50000",
                "x-quota-queued-tasks-remaining", "49999");

        Fivexer client = client();
        client.tasks().create(new CreateTask(List.of("english")));

        assertNotNull(client.getQuota());
        assertEquals(300, client.getQuota().getTaskRateLimit());
        assertEquals(299, client.getQuota().getTaskRateRemaining());
        assertEquals(50000, client.getQuota().getQueuedTasksLimit());
        assertEquals(49999, client.getQuota().getQueuedTasksRemaining());
        assertNull(client.getQuota().getWorkersLimit());
    }

    @Test
    void a_rate_limited_request_raises_with_retry_after_and_quota_attached() {
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"task creation rate above 300/min\"}}",
                "retry-after", "60",
                "x-quota-task-rate-limit", "300",
                "x-quota-task-rate-remaining", "0");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().tasks().create(new CreateTask(List.of("x"))));
        assertEquals(429, ex.getStatusCode());
        assertEquals("rate_limited", ex.getCode());
        assertEquals(60.0, ex.getRetryAfterSeconds());
        assertEquals(0, ex.getQuota().getTaskRateRemaining());
    }

    @Test
    void an_invalid_retry_after_value_yields_null() {
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow down\"}}",
                "retry-after", "not-a-number");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().tasks().list(null));
        assertNull(ex.getRetryAfterSeconds());
    }

    @Test
    void a_validation_error_reports_the_code_and_message() {
        enqueueJson(400, "{\"error\":{\"code\":\"validation_failed\","
                + "\"message\":\"body must have required property 'tags'\"}}");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().tasks().list(null));
        assertEquals(400, ex.getStatusCode());
        assertEquals("validation_failed", ex.getCode());
        assertTrue(ex.getMessage().contains("tags"));
    }

    @Test
    void an_unparseable_error_body_falls_back_to_unknown_code() {
        enqueueJson(500, "<html>oops</html>");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().tasks().list(null));
        assertEquals(500, ex.getStatusCode());
        assertEquals("unknown_error", ex.getCode());
    }

    @Test
    void retry_transparently_retries_once_on_5xx_then_succeeds() throws Exception {
        enqueueJson(503, "{\"error\":{\"code\":\"internal_error\",\"message\":\"transient\"}}");
        enqueueJson(200, "{\"tasks\":[],\"nextCursor\":null,\"hasMore\":false}");

        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", 1);
        var page = client.tasks().list(null);

        assertEquals(0, page.getTasks().size());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void retry_respects_retry_after_with_no_sleep_when_zero() throws Exception {
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow down\"}}",
                "retry-after", "0");
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", 1);
        var task = client.tasks().create(new CreateTask(List.of("x")));

        assertEquals("task_1", task.getId());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void retry_does_not_retry_when_max_retries_is_zero() throws Exception {
        enqueueJson(503, "{\"error\":{\"code\":\"internal_error\",\"message\":\"down\"}}");

        assertThrows(FivexerApiException.class, () -> client().tasks().list(null));
        assertEquals(1, server.getRequestCount());
    }

    @Test
    void a_retried_post_sends_the_same_idempotency_key_both_times() throws Exception {
        // First attempt: 503 (retryable). Second attempt: 202 (success).
        enqueueJson(503, "{\"error\":{\"code\":\"internal_error\",\"message\":\"transient\"}}");
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", 1);
        client.tasks().create(new CreateTask(List.of("english")));

        // Both requests must carry the SAME idempotency key so the server can deduplicate.
        RecordedRequest first = server.takeRequest();
        RecordedRequest second = server.takeRequest();
        String key1 = first.getHeader("Idempotency-Key");
        String key2 = second.getHeader("Idempotency-Key");
        assertNotNull(key1, "first POST must carry Idempotency-Key");
        assertNotNull(key2, "second POST must carry Idempotency-Key");
        assertEquals(key1, key2, "idempotency key must be stable across retries");
    }

    @Test
    void constructor_rejects_missing_base_url_and_key() {
        assertThrows(IllegalArgumentException.class, () -> new Fivexer("", "sk_x"));
        assertThrows(IllegalArgumentException.class, () -> new Fivexer("https://api.fivexer.test", ""));
    }

    @Test
    void base_url_trailing_slash_is_stripped() {
        Fivexer client = new Fivexer("https://api.fivexer.test/", "sk_x");
        assertEquals("https://api.fivexer.test", client.getBaseUrl());
    }

    @Test
    void api_exception_to_string_includes_status_and_code() {
        enqueueJson(404, "{\"error\":{\"code\":\"not_found\",\"message\":\"task not found\"}}");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().tasks().get("missing"));
        String s = ex.toString();
        assertTrue(s.contains("404"));
        assertTrue(s.contains("not_found"));
    }

    @Test
    void retry_sleeps_for_a_positive_retry_after_then_succeeds() throws Exception {
        // A tiny positive retry-after exercises the real sleep path (1ms, well within budget).
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow down\"}}",
                "retry-after", "0.001");
        enqueueJson(200, "{\"workers\":[],\"count\":0}");

        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", 1);
        var result = client.workers().list();

        assertEquals(0, result.getCount());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void a_non_integer_quota_header_value_is_ignored_safely() {
        // One valid header (so quota is populated) + one malformed value (must not throw).
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}",
                "x-quota-workers-limit", "50",
                "x-quota-task-rate-limit", "not-a-number");

        Fivexer client = client();
        client.tasks().create(new CreateTask(List.of("x")));

        assertNotNull(client.getQuota());
        assertEquals(50, client.getQuota().getWorkersLimit());
        assertNull(client.getQuota().getTaskRateLimit());
    }

    @Test
    void injected_http_client_constructor_works() {
        enqueueJson(200, "{\"workers\":[\"a\"],\"count\":1}");

        okhttp3.OkHttpClient injected = new okhttp3.OkHttpClient();
        Fivexer client = new Fivexer(server.url("/").toString(), "sk_test_x", injected);
        var result = client.workers().list();

        assertEquals(1, result.getCount());
    }

    @Test
    void close_is_a_safe_noop() {
        Fivexer client = client();
        assertDoesNotThrow(client::close);
    }
}
