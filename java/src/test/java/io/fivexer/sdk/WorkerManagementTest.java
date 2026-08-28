package io.fivexer.sdk;

import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerList;
import io.fivexer.sdk.model.WorkerMetrics;
import io.fivexer.sdk.model.WorkerQueue;
import io.fivexer.sdk.model.WorkerTimeEntriesResult;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Worker management: upsert, list, queue inspection, and removal.
 */
class WorkerManagementTest extends MockServerBase {

    @Test
    void upserting_a_worker_without_an_id_gets_one_assigned() throws Exception {
        enqueueJson(200, "{\"id\":\"w_abc\"}");

        String workerId = client().workers().upsert(new UpsertWorker());

        assertEquals("w_abc", workerId);
        assertEquals("{}", takeRequest().getBody().readUtf8());
    }

    @Test
    void upserting_a_worker_with_tags_and_routing_weights_serializes_them() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").tags(List.of("english", "billing"))
                .routingWeights(Map.of("english", 100.0)));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"id\":\"agent_1\""));
        assertTrue(body.contains("\"tags\":[\"english\",\"billing\"]"));
        assertTrue(body.contains("\"routingWeights\":{\"english\":100"));
    }

    @Test
    void upserting_a_field_worker_includes_geo_fields() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").ip("203.0.113.5")
                .latitude(59.4).longitude(24.7).maxTravelDistanceKm(15));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"ip\":\"203.0.113.5\""));
        assertTrue(body.contains("\"latitude\":59.4"));
        assertTrue(body.contains("\"longitude\":24.7"));
        assertTrue(body.contains("\"maxTravelDistanceKm\":15"));
    }

    @Test
    void listing_workers_returns_ids_and_count() {
        enqueueJson(200, "{\"workers\":[\"agent_1\",\"agent_2\"],\"count\":2}");

        WorkerList result = client().workers().list();

        assertEquals(List.of("agent_1", "agent_2"), result.getWorkers());
        assertEquals(2, result.getCount());
    }

    @Test
    void inspecting_a_worker_queue_returns_their_task_ids() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[\"task_8fk2\",\"task_9\"]}");

        WorkerQueue queue = client().workers().queue("agent_1");

        assertEquals("agent_1", queue.getWorkerId());
        assertEquals(List.of("task_8fk2", "task_9"), queue.getTaskIds());
        assertEquals("/v1/workers/agent_1/queue", takeRequest().getPath());
    }

    @Test
    void one_workers_metrics_report_today_and_the_rolling_window() throws Exception {
        // The operator's copy of what the worker sees about themselves — same numbers, one log.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"today\":{\"since\":\"2026-07-24T00:00:00Z\","
                + "\"completedTasks\":7,\"shiftCount\":1,\"onShiftMs\":28800000,\"breakCount\":1,"
                + "\"totalBreakMs\":1800000,\"longestBreakMs\":1800000,\"workingMs\":27000000},"
                + "\"window\":{\"from\":\"2026-07-17T00:00:00Z\",\"to\":\"2026-07-24T00:00:00Z\","
                + "\"days\":[{\"day\":\"2026-07-23\",\"completed\":12}],\"medianWaitMs\":4200,"
                + "\"medianCycleMs\":90000,\"shiftCount\":5,\"onShiftMs\":144000000,"
                + "\"breakMs\":9000000,\"workingMs\":135000000,\"offered\":20,\"accepted\":16,"
                + "\"rejected\":2,\"completed\":14,\"failed\":1,\"expired\":1,\"released\":0,"
                + "\"acceptanceRate\":0.8}}");

        WorkerMetrics metrics = client().workers().metrics("agent_1", "7d");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workers/agent_1/metrics", pathOf(request));
        assertEquals("window=7d", queryOf(request));
        assertEquals("agent_1", metrics.getWorkerId());
        assertEquals("2026-07-24T00:00:00Z", metrics.getToday().getSince());
        assertEquals(7, metrics.getToday().getCompletedTasks());
        assertEquals(1, metrics.getToday().getShiftCount());
        assertEquals(28800000L, metrics.getToday().getOnShiftMs());
        assertEquals(1, metrics.getToday().getBreakCount());
        assertEquals(1800000L, metrics.getToday().getTotalBreakMs());
        assertEquals(1800000L, metrics.getToday().getLongestBreakMs());
        // Breaks are time inside a shift: 8h on shift less a 30m break is 7h30 worked.
        assertEquals(27000000L, metrics.getToday().getWorkingMs());
        assertEquals("2026-07-17T00:00:00Z", metrics.getWindow().getFrom());
        assertEquals("2026-07-24T00:00:00Z", metrics.getWindow().getTo());
        assertEquals("2026-07-23", metrics.getWindow().getDays().get(0).getDay());
        assertEquals(4200L, metrics.getWindow().getMedianWaitMs());
        assertEquals(90000L, metrics.getWindow().getMedianCycleMs());
        assertEquals(5, metrics.getWindow().getShiftCount());
        assertEquals(144000000L, metrics.getWindow().getOnShiftMs());
        assertEquals(9000000L, metrics.getWindow().getBreakMs());
        assertEquals(135000000L, metrics.getWindow().getWorkingMs());
        assertEquals(20, metrics.getWindow().getOffered());
        assertEquals(16, metrics.getWindow().getAccepted());
        assertEquals(2, metrics.getWindow().getRejected());
        assertEquals(14, metrics.getWindow().getCompleted());
        assertEquals(1, metrics.getWindow().getFailed());
        assertEquals(1, metrics.getWindow().getExpired());
        assertEquals(0, metrics.getWindow().getReleased());
        assertEquals(0.8, metrics.getWindow().getAcceptanceRate());
    }

    @Test
    void a_worker_who_was_never_offered_anything_has_no_acceptance_rate() throws Exception {
        // Null, not 0.0 — "nothing was measured" and "they accepted none of it" differ.
        enqueueJson(200, "{\"workerId\":\"agent_9\",\"today\":{\"since\":\"x\"},"
                + "\"window\":{\"from\":\"a\",\"to\":\"b\",\"days\":null,\"medianWaitMs\":null,"
                + "\"medianCycleMs\":null,\"acceptanceRate\":null}}");

        WorkerMetrics metrics = client().workers().metrics("agent_9");

        assertEquals("/v1/workers/agent_9/metrics", takeRequest().getPath());
        assertNull(metrics.getWindow().getAcceptanceRate());
        assertNull(metrics.getWindow().getMedianWaitMs());
        assertNull(metrics.getWindow().getMedianCycleMs());
        assertNull(metrics.getWindow().getDays());
        assertEquals(0, metrics.getToday().getCompletedTasks());
    }

    @Test
    void the_time_entries_log_records_shifts_and_the_breaks_inside_them() throws Exception {
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

        WorkerTimeEntriesResult log = client().workers()
                .timeEntries("agent_1", "2026-07-17T00:00:00Z", "2026-07-24T00:00:00Z");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workers/agent_1/time-entries", pathOf(request));
        assertTrue(queryOf(request).contains("from=2026-07-17"));
        assertTrue(queryOf(request).contains("to=2026-07-24"));
        assertEquals("agent_1", log.getWorkerId());
        assertEquals("2026-07-17T00:00:00Z", log.getFrom());
        assertEquals("2026-07-24T00:00:00Z", log.getTo());
        assertEquals("shift", log.getEntries().get(0).getType());
        assertEquals("2026-07-23T08:00:00Z", log.getEntries().get(0).getStartedAt());
        assertEquals("2026-07-23T16:00:00Z", log.getEntries().get(0).getEndedAt());
        assertEquals(28800000L, log.getEntries().get(0).getDurationMs());
        assertEquals("portal", log.getEntries().get(0).getSource());
        assertEquals("manual", log.getEntries().get(0).getEndReason());
        assertEquals("break", log.getEntries().get(1).getType());
        assertEquals("lunch", log.getEntries().get(1).getReason());
        assertNull(log.getEntries().get(1).getSource());
        assertEquals(1, log.getTotals().getShiftCount());
        assertEquals(28800000L, log.getTotals().getOnShiftMs());
        assertEquals(1, log.getTotals().getBreakCount());
        assertEquals(1800000L, log.getTotals().getBreakMs());
        // workingMs is onShiftMs - breakMs: a break is time inside the shift, not beside it.
        assertEquals(27000000L, log.getTotals().getWorkingMs());
    }

    @Test
    void a_shift_the_platform_closed_says_so_and_an_open_one_has_no_end() throws Exception {
        // `timeout` means the platform clocked out a silent unattended worker, not a judgement.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"from\":\"a\",\"to\":\"b\",\"entries\":["
                + "{\"type\":\"shift\",\"startedAt\":\"2026-07-23T08:00:00Z\",\"endedAt\":null,"
                + "\"durationMs\":600000,\"source\":\"portal\",\"endReason\":null},"
                + "{\"type\":\"shift\",\"startedAt\":\"2026-07-22T08:00:00Z\","
                + "\"endedAt\":\"2026-07-22T09:00:00Z\",\"durationMs\":3600000,"
                + "\"source\":\"portal\",\"endReason\":\"timeout\"}],\"totals\":{}}");

        WorkerTimeEntriesResult log = client().workers().timeEntries("agent_1");

        assertEquals("/v1/workers/agent_1/time-entries", takeRequest().getPath());
        assertNull(log.getEntries().get(0).getEndedAt());
        assertNull(log.getEntries().get(0).getEndReason());
        assertEquals("timeout", log.getEntries().get(1).getEndReason());
        assertEquals(0, log.getTotals().getShiftCount());
    }

    @Test
    void removing_a_worker_hits_delete() throws Exception {
        enqueueEmpty(204);

        client().workers().remove("agent_1");

        RecordedRequest req = takeRequest();
        assertEquals("DELETE", req.getMethod());
        assertEquals("/v1/workers/agent_1", req.getPath());
    }

    @Test
    void upserting_a_worker_at_the_plan_limit_raises() {
        enqueueJson(402, "{\"error\":{\"code\":\"plan_limit_exceeded\",\"message\":\"worker limit of 25 reached\"}}",
                "x-quota-workers-limit", "25", "x-quota-workers-remaining", "0");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().workers().upsert(new UpsertWorker("agent_1")));
        assertEquals(402, ex.getStatusCode());
        assertEquals("plan_limit_exceeded", ex.getCode());
        assertEquals(25, ex.getQuota().getWorkersLimit());
        assertEquals(0, ex.getQuota().getWorkersRemaining());
    }
}
