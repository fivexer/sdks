package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.TaskAction;
import io.fivexer.sdk.model.TeamPresence;
import io.fivexer.sdk.model.WorkerBreakEnded;
import io.fivexer.sdk.model.WorkerBreakStarted;
import io.fivexer.sdk.model.WorkerBreakToday;
import io.fivexer.sdk.model.WorkerLogin;
import io.fivexer.sdk.model.WorkerMetricsToday;
import io.fivexer.sdk.model.WorkerQueue;
import io.fivexer.sdk.model.WorkerSessionToken;
import io.fivexer.sdk.model.WorkerTaskDetail;
import io.fivexer.sdk.model.WorkerTimeEntriesResult;
import java.util.List;
import java.util.Map;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * The worker portal plane: one worker acting on their own queue with a {@code wt_} token.
 *
 * <p>The security property this plane exists for is that the token is scoped to a single worker
 * and cannot reach task creation or worker management. These tests hold the client to its half
 * of that: it never sends a workspace key, and it will not guess a worker id it was not given.
 */
class WorkerPortalTest extends MockServerBase {

    // ---- login / logout ----

    @Test
    void logging_in_adopts_the_token_and_the_worker_id() throws Exception {
        enqueueJson(200, "{\"token\":\"wt_s3ss10n\"}");
        FivexerWorker worker = anonymousWorker();

        WorkerSessionToken session = worker.login(new WorkerLogin("ws_1", "agent_1", "4821"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/worker-auth/login", request.getPath());
        assertEquals("wt_s3ss10n", session.getToken());
        assertEquals("wt_s3ss10n", worker.getSessionToken());
        assertEquals("agent_1", worker.getWorkerId());
    }

    @Test
    void the_login_request_itself_carries_no_authorization() throws Exception {
        // There is no token yet; sending an empty bearer would be a malformed request.
        enqueueJson(200, "{\"token\":\"wt_s3ss10n\"}");

        anonymousWorker().login(new WorkerLogin("ws_1", "agent_1", "4821"));

        assertNull(takeRequest().getHeader("Authorization"));
    }

    @Test
    void a_wrong_pin_leaves_the_client_unauthenticated() {
        enqueueJson(401, "{\"error\":{\"code\":\"invalid_credentials\","
                + "\"message\":\"invalid worker credentials\"}}");
        FivexerWorker worker = anonymousWorker();

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> worker.login(new WorkerLogin("ws_1", "agent_1", "0000")));

        assertEquals("invalid_credentials", error.getCode());
        assertNull(worker.getSessionToken());
    }

    @Test
    void logging_out_forgets_the_token() throws Exception {
        enqueueEmpty(204);
        FivexerWorker worker = worker();

        worker.logout();

        assertEquals("/v1/worker-auth/logout", takeRequest().getPath());
        assertNull(worker.getSessionToken());
    }

    @Test
    void a_resumed_session_authenticates_with_the_stored_token() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[]}");

        worker().queue();

        assertEquals("Bearer wt_s3ss10n", takeRequest().getHeader("Authorization"));
    }

    @Test
    void a_token_can_be_adopted_after_construction() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_7\",\"taskIds\":[]}");
        FivexerWorker worker = anonymousWorker();

        worker.setToken("wt_restored", "agent_7");
        worker.queue();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/workers/agent_7/queue", request.getPath());
        assertEquals("Bearer wt_restored", request.getHeader("Authorization"));
    }

    @Test
    void adopting_a_token_without_a_worker_id_keeps_the_existing_one() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[]}");
        FivexerWorker worker = worker();

        worker.setToken("wt_rotated", null);
        worker.queue();

        assertEquals("/v1/portal/workers/agent_1/queue", takeRequest().getPath());
    }

    @Test
    void the_client_exposes_its_base_url_without_a_trailing_slash() {
        FivexerWorker worker = new FivexerWorker("https://api.fivexer.test/");

        assertEquals("https://api.fivexer.test", worker.getBaseUrl());
    }

    @Test
    void a_base_url_is_required() {
        assertThrows(IllegalArgumentException.class, () -> new FivexerWorker(""));
        assertThrows(IllegalArgumentException.class, () -> new FivexerWorker(null));
    }

    @Test
    void the_client_can_be_closed_in_a_try_with_resources() {
        try (FivexerWorker worker = new FivexerWorker("https://api.fivexer.test", "wt_1", "agent_1")) {
            assertEquals("agent_1", worker.getWorkerId());
        }
    }

    // ---- acting without a worker id ----

    @Test
    void acting_before_logging_in_fails_without_reaching_the_network() {
        // Guessing an id here would let one worker act as another; refusing locally is the point.
        FivexerWorker worker = anonymousWorker();

        FivexerApiException error = assertThrows(FivexerApiException.class, worker::queue);

        assertEquals("worker_id_required", error.getCode());
        assertEquals(400, error.getStatusCode());
        assertEquals(0, server.getRequestCount());
    }

    @Test
    void an_explicit_worker_id_works_without_a_prior_login() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");

        anonymousWorker().accept("task_8fk2", "agent_9");

        assertEquals("agent_9", bodyOf(takeRequest()).get("workerId").getAsString());
    }

    // ---- queue and task actions ----

    @Test
    void a_worker_reads_their_own_queue() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[\"task_8fk2\"]}");

        WorkerQueue queue = worker().queue();

        assertEquals("/v1/portal/workers/agent_1/queue", takeRequest().getPath());
        assertEquals(List.of("task_8fk2"), queue.getTaskIds());
        assertEquals("agent_1", queue.getWorkerId());
    }

    @Test
    void task_detail_includes_the_rich_context_the_worker_needs() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"pending\",\"tags\":[\"english\"],"
                + "\"priority\":90,\"title\":\"Refund request\",\"description\":\"Order 41\","
                + "\"context\":{\"orderId\":\"41\"},"
                + "\"references\":[{\"id\":\"ref_1\",\"url\":\"https://crm/o/41\",\"label\":\"Order 41\"}],"
                + "\"createdAt\":1750000000000}");

        WorkerTaskDetail detail = worker().taskDetail("task_8fk2");

        assertEquals("task_8fk2", detail.getId());
        assertEquals("pending", detail.getStatus());
        assertEquals(List.of("english"), detail.getTags());
        assertEquals(90.0, detail.getPriority());
        assertEquals("Refund request", detail.getTitle());
        assertEquals("Order 41", detail.getDescription());
        assertEquals("41", detail.getContext().get("orderId"));
        assertEquals("Order 41", detail.getReferences().get(0).getLabel());
        assertEquals(1750000000000L, detail.getCreatedAt());
    }

    @Test
    void another_workers_task_is_not_visible() {
        enqueueJson(404, "{\"error\":{\"code\":\"not_found\",\"message\":\"task not found\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> worker().taskDetail("task_other"));

        assertEquals(404, error.getStatusCode());
    }

    @Test
    void accepting_a_task_reports_the_new_status() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");

        TaskAction result = worker().accept("task_8fk2");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/tasks/task_8fk2/accept", request.getPath());
        assertEquals("agent_1", bodyOf(request).get("workerId").getAsString());
        assertEquals("accepted", result.getStatus());
        assertEquals("task_8fk2", result.getId());
    }

    @Test
    void rejecting_a_task_requeues_it_for_others() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}");

        assertEquals("queued", worker().reject("task_8fk2").getStatus());
        assertEquals("/v1/portal/tasks/task_8fk2/reject", takeRequest().getPath());
    }

    @Test
    void rejecting_on_behalf_of_an_explicit_worker_uses_that_id() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}");

        worker().reject("task_8fk2", "agent_9");

        assertEquals("agent_9", bodyOf(takeRequest()).get("workerId").getAsString());
    }

    @Test
    void completing_a_task_can_attach_a_result() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"completed\"}");

        assertEquals("completed", worker().complete("task_8fk2", Map.of("refunded", true)).getStatus());

        RecordedRequest request = takeRequest();
        assertTrue(bodyOf(request).getAsJsonObject("result").get("refunded").getAsBoolean());
    }

    @Test
    void completing_without_a_result_omits_the_field() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"completed\"}");

        worker().complete("task_8fk2");

        assertFalse(bodyOf(takeRequest()).has("result"));
    }

    @Test
    void completing_on_behalf_of_an_explicit_worker_uses_that_id() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"completed\"}");

        worker().complete("task_8fk2", Map.of("ok", true), "agent_9");

        assertEquals("agent_9", bodyOf(takeRequest()).get("workerId").getAsString());
    }

    // ---- breaks ----

    @Test
    void starting_a_break_pauses_routing_to_this_worker() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"onBreak\":true,\"since\":\"2026-07-24T11:30:00Z\"}");

        WorkerBreakStarted started = worker().startBreak("lunch");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/breaks/start", request.getPath());
        assertEquals("lunch", bodyOf(request).get("reason").getAsString());
        assertTrue(started.isOnBreak());
        assertEquals("agent_1", started.getWorkerId());
        assertEquals("2026-07-24T11:30:00Z", started.getSince());
    }

    @Test
    void starting_a_break_without_a_reason_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"onBreak\":true,\"since\":\"x\"}");

        worker().startBreak();

        assertEquals(0, bodyOf(takeRequest()).size());
    }

    @Test
    void starting_a_second_break_is_refused() {
        enqueueJson(409, "{\"error\":{\"code\":\"break_already_open\","
                + "\"message\":\"a break is already open\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> worker().startBreak());

        assertEquals(409, error.getStatusCode());
        assertEquals("break_already_open", error.getCode());
    }

    @Test
    void ending_a_break_resumes_routing() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"onBreak\":false,"
                + "\"endedAt\":\"2026-07-24T12:00:00Z\"}");

        WorkerBreakEnded ended = worker().endBreak();

        assertEquals("/v1/portal/breaks/end", takeRequest().getPath());
        assertFalse(ended.isOnBreak());
        assertEquals("2026-07-24T12:00:00Z", ended.getEndedAt());
        assertEquals("agent_1", ended.getWorkerId());
    }

    @Test
    void ending_a_break_when_none_is_open_is_not_an_error() throws Exception {
        // The API answers 204; a portal UI calling this on a timer must not see an exception.
        enqueueEmpty(204);

        assertNull(worker().endBreak());
    }

    @Test
    void todays_breaks_include_the_one_still_running() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"2026-07-24T00:00:00Z\","
                + "\"breaks\":[{\"id\":\"brk_1\",\"startedAt\":\"2026-07-24T11:30:00Z\","
                + "\"endedAt\":\"2026-07-24T12:00:00Z\",\"reason\":\"lunch\",\"durationMs\":1800000}],"
                + "\"active\":{\"id\":\"brk_2\",\"startedAt\":\"2026-07-24T13:00:00Z\","
                + "\"endedAt\":null,\"reason\":null,\"durationMs\":0},"
                + "\"completedTasks\":7,\"totalBreakMs\":1800000}");

        WorkerBreakToday today = worker().breaksToday();

        assertEquals("brk_2", today.getActive().getId());
        assertNull(today.getActive().getEndedAt());
        assertEquals("brk_1", today.getBreaks().get(0).getId());
        assertEquals("lunch", today.getBreaks().get(0).getReason());
        assertEquals(1800000L, today.getBreaks().get(0).getDurationMs());
        assertEquals("2026-07-24T11:30:00Z", today.getBreaks().get(0).getStartedAt());
        assertEquals(7, today.getCompletedTasks());
        assertEquals(1800000L, today.getTotalBreakMs());
        assertEquals("2026-07-24T00:00:00Z", today.getSince());
        assertEquals("agent_1", today.getWorkerId());
    }

    @Test
    void a_worker_not_currently_on_break_has_no_active_entry() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"x\",\"breaks\":[],\"active\":null}");

        assertNull(worker().breaksToday().getActive());
    }

    @Test
    void todays_metrics_separate_working_time_from_break_time() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"2026-07-24T00:00:00Z\","
                + "\"completedTasks\":7,\"breakCount\":1,\"totalBreakMs\":1800000,"
                + "\"longestBreakMs\":1800000,\"workingMs\":25200000}");

        WorkerMetricsToday metrics = worker().metricsToday();

        assertEquals("/v1/portal/metrics/today", takeRequest().getPath());
        assertEquals(25200000L, metrics.getWorkingMs());
        assertEquals(1800000L, metrics.getTotalBreakMs());
        assertEquals(1800000L, metrics.getLongestBreakMs());
        assertEquals(1, metrics.getBreakCount());
        assertEquals(7, metrics.getCompletedTasks());
        assertEquals("agent_1", metrics.getWorkerId());
        assertEquals("2026-07-24T00:00:00Z", metrics.getSince());
    }

    @Test
    void todays_metrics_also_report_shift_time_when_the_server_has_a_shift_log() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"2026-07-24T00:00:00Z\","
                + "\"completedTasks\":7,\"breakCount\":1,\"totalBreakMs\":1800000,"
                + "\"longestBreakMs\":1800000,\"workingMs\":27000000,\"onShiftMs\":28800000,"
                + "\"shiftCount\":1}");

        WorkerMetricsToday metrics = worker().metricsToday();

        assertEquals(28800000L, metrics.getOnShiftMs());
        assertEquals(1, metrics.getShiftCount());
        assertEquals(27000000L, metrics.getWorkingMs());
    }

    @Test
    void an_older_server_without_a_shift_log_reports_no_shift_time() throws Exception {
        // onShiftMs/shiftCount are additive: absent means the deployment predates the shift log.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"x\",\"completedTasks\":0,"
                + "\"breakCount\":0,\"totalBreakMs\":0,\"longestBreakMs\":0,\"workingMs\":100}");

        WorkerMetricsToday metrics = worker().metricsToday();

        assertEquals(0L, metrics.getOnShiftMs());
        assertEquals(0, metrics.getShiftCount());
    }

    @Test
    void a_worker_reads_their_own_time_log_with_no_parameters_to_narrow_it() throws Exception {
        // Same record an operator reads — that parity is what keeps it a timesheet.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"from\":\"2026-07-17T00:00:00Z\","
                + "\"to\":\"2026-07-24T00:00:00Z\",\"entries\":["
                + "{\"type\":\"shift\",\"startedAt\":\"2026-07-23T08:00:00Z\","
                + "\"endedAt\":\"2026-07-23T16:00:00Z\",\"durationMs\":28800000,"
                + "\"source\":\"portal\",\"endReason\":\"manual\"},"
                + "{\"type\":\"break\",\"startedAt\":\"2026-07-23T11:30:00Z\","
                + "\"endedAt\":\"2026-07-23T12:00:00Z\",\"durationMs\":1800000,"
                + "\"reason\":\"lunch\"}],"
                + "\"totals\":{\"shiftCount\":1,\"onShiftMs\":28800000,\"breakCount\":1,"
                + "\"breakMs\":1800000,\"workingMs\":27000000}}");

        WorkerTimeEntriesResult log = worker().timeEntries();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/me/time-entries", request.getPath());
        assertEquals("GET", request.getMethod());
        assertEquals("agent_1", log.getWorkerId());
        assertEquals("shift", log.getEntries().get(0).getType());
        assertEquals("manual", log.getEntries().get(0).getEndReason());
        assertEquals("lunch", log.getEntries().get(1).getReason());
        assertEquals(27000000L, log.getTotals().getWorkingMs());
        assertEquals(1, log.getTotals().getBreakCount());
        assertEquals(28800000L, log.getTotals().getOnShiftMs());
        assertEquals(1800000L, log.getTotals().getBreakMs());
        assertEquals(1, log.getTotals().getShiftCount());
        assertEquals("2026-07-17T00:00:00Z", log.getFrom());
        assertEquals("2026-07-24T00:00:00Z", log.getTo());
        assertEquals("2026-07-23T08:00:00Z", log.getEntries().get(0).getStartedAt());
        assertEquals("2026-07-23T16:00:00Z", log.getEntries().get(0).getEndedAt());
        assertEquals(28800000L, log.getEntries().get(0).getDurationMs());
        assertEquals("portal", log.getEntries().get(0).getSource());
    }

    @Test
    void a_worker_can_see_who_else_is_on_shift() throws Exception {
        enqueueJson(200, "{\"workers\":[{\"workerId\":\"agent_1\",\"label\":\"Ada\","
                + "\"status\":\"working\"}],"
                + "\"counts\":{\"working\":1,\"onBreak\":0,\"paused\":0,\"total\":1}}");

        TeamPresence presence = worker().teamPresence();

        assertEquals("/v1/portal/team/presence", takeRequest().getPath());
        assertEquals(1, presence.getCounts().getWorking());
        assertEquals(1, presence.getCounts().getTotal());
        assertEquals("Ada", presence.getWorkers().get(0).getLabel());
    }

    // ---- transport behaviour ----

    @Test
    void a_portal_action_is_not_replayed_after_a_server_error() throws Exception {
        // Portal actions carry no idempotency key, so retrying an accept could double-accept.
        enqueueJson(503, "{\"error\":{\"code\":\"internal_error\",\"message\":\"boom\"}}");
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");
        FivexerWorker retrying = new FivexerWorker(server.url("/").toString(), "wt_1", "agent_1", null, 1);

        assertThrows(FivexerApiException.class, () -> retrying.accept("task_8fk2"));

        assertEquals(1, server.getRequestCount());
    }

    @Test
    void a_transient_read_failure_is_retried() throws Exception {
        enqueueJson(503, "{\"error\":{\"code\":\"internal_error\",\"message\":\"boom\"}}",
                "retry-after", "0.001");
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[]}");
        FivexerWorker retrying = new FivexerWorker(server.url("/").toString(), "wt_1", "agent_1", null, 1);

        assertEquals("agent_1", retrying.queue().getWorkerId());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void an_unparseable_error_body_still_raises_a_structured_error() {
        enqueueJson(500, "<html>gateway</html>");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> worker().queue());

        assertEquals("unknown_error", error.getCode());
        assertEquals(500, error.getStatusCode());
        assertEquals("http 500", error.getMessage());
    }

    @Test
    void an_empty_worker_id_is_treated_as_no_worker_id() {
        // A blank string would build a URL like /portal/workers//queue and 404 confusingly.
        FivexerWorker worker = new FivexerWorker(server.url("/").toString(), "wt_1", "", null, 0);

        assertThrows(FivexerApiException.class, worker::queue);
        assertEquals(0, server.getRequestCount());
    }

    @Test
    void a_read_is_retried_only_up_to_the_configured_limit() throws Exception {
        enqueueJson(503, "{\"error\":{\"code\":\"busy\",\"message\":\"b\"}}");
        enqueueJson(503, "{\"error\":{\"code\":\"busy\",\"message\":\"b\"}}");
        FivexerWorker retrying = new FivexerWorker(server.url("/").toString(), "wt_1", "agent_1", null, 1);

        assertThrows(FivexerApiException.class, retrying::queue);

        assertEquals(2, server.getRequestCount());
    }

    @Test
    void a_rate_limited_read_is_retried_after_the_advertised_delay() throws Exception {
        // A 1ms Retry-After exercises the real sleep path without slowing the suite.
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow down\"}}",
                "retry-after", "0.001");
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[]}");
        FivexerWorker retrying = new FivexerWorker(server.url("/").toString(), "wt_1", "agent_1", null, 1);

        assertEquals("agent_1", retrying.queue().getWorkerId());
        assertEquals(2, server.getRequestCount());
    }

    @Test
    void a_retryable_read_without_a_retry_after_header_is_retried_immediately() throws Exception {
        enqueueJson(500, "{\"error\":{\"code\":\"boom\",\"message\":\"b\"}}");
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[]}");
        FivexerWorker retrying = new FivexerWorker(server.url("/").toString(), "wt_1", "agent_1", null, 1);

        assertEquals("agent_1", retrying.queue().getWorkerId());
    }

    @Test
    void an_error_body_whose_error_field_is_not_an_object_falls_back_to_defaults() {
        enqueueJson(400, "{\"error\":\"just a string\"}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> worker().queue());

        assertEquals("unknown_error", error.getCode());
    }

    @Test
    void an_error_object_missing_a_message_still_reports_the_status() {
        enqueueJson(422, "{\"error\":{\"code\":\"validation_failed\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> worker().queue());

        assertEquals("validation_failed", error.getCode());
        assertEquals("http 422", error.getMessage());
    }

    @Test
    void reading_a_queue_for_an_explicitly_named_worker_ignores_the_session_worker() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_9\",\"taskIds\":[]}");

        worker().queue("agent_9");

        assertEquals("/v1/portal/workers/agent_9/queue", takeRequest().getPath());
    }

    @Test
    void accepting_on_behalf_of_an_explicit_worker_uses_that_id() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");

        worker().accept("task_8fk2", "agent_9");

        assertEquals("agent_9", bodyOf(takeRequest()).get("workerId").getAsString());
    }
}
