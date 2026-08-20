package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.StatsTimeseriesResult;
import io.fivexer.sdk.model.Task;
import io.fivexer.sdk.model.TeamPresence;
import io.fivexer.sdk.model.WorkerStatsResult;
import io.fivexer.sdk.model.WorkspaceBreakMetrics;
import io.fivexer.sdk.model.WorkspaceStats;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Live stats, historical stats, and the supervisor views of presence and breaks.
 *
 * <p>The history and presence endpoints need the control plane; a data-plane-only deployment
 * answers 501, which callers have to be able to tell apart from a real failure.
 */
class StatsAndPresenceTest extends MockServerBase {

    @Test
    void workspace_stats_report_per_worker_load_and_the_live_balancer_policy() throws Exception {
        enqueueJson(200, "{\"plan\":\"pro\",\"tasks\":{\"queued\":3,\"pending\":1},\"workers\":2,"
                + "\"queue\":{\"oldestWaitingMs\":84000,\"perWorker\":["
                + "{\"workerId\":\"agent_1\",\"backlog\":2,\"maxBacklogSize\":5,\"available\":true},"
                + "{\"workerId\":\"agent_2\",\"backlog\":0,\"maxBacklogSize\":5,\"available\":false}]},"
                + "\"meter\":{\"period\":\"2026-07\",\"matchedTasks\":421,\"includedTasksPerMonth\":50000},"
                + "\"matching\":{\"fairness\":\"balanced\",\"maxTasksPerWindow\":20,\"windowMs\":3600000}}");

        WorkspaceStats stats = client().stats();

        assertEquals("pro", stats.getPlan());
        assertEquals(3, stats.getTasks().get("queued"));
        assertEquals(2, stats.getWorkers());
        assertEquals(84000L, stats.getQueue().getOldestWaitingMs());
        assertEquals("agent_1", stats.getQueue().getPerWorker().get(0).getWorkerId());
        assertEquals(2, stats.getQueue().getPerWorker().get(0).getBacklog());
        assertEquals(5, stats.getQueue().getPerWorker().get(0).getMaxBacklogSize());
        assertTrue(stats.getQueue().getPerWorker().get(0).isAvailable());
        assertFalse(stats.getQueue().getPerWorker().get(1).isAvailable());
        assertEquals("balanced", stats.getMatching().getFairness());
        assertEquals(20, stats.getMatching().getMaxTasksPerWindow());
        assertEquals(3600000L, stats.getMatching().getWindowMs());
        assertEquals("2026-07", stats.getMeter().getPeriod());
        assertEquals(421, stats.getMeter().getMatchedTasks());
        assertEquals(50000, stats.getMeter().getIncludedTasksPerMonth());
    }

    @Test
    void an_empty_queue_reports_no_oldest_wait_rather_than_zero() throws Exception {
        // Zero would read as "a task has been waiting 0ms", which is a different fact.
        enqueueJson(200, "{\"plan\":\"free\",\"tasks\":{},\"workers\":0,"
                + "\"queue\":{\"oldestWaitingMs\":null,\"perWorker\":[]},"
                + "\"meter\":{\"period\":\"2026-07\",\"matchedTasks\":0,\"includedTasksPerMonth\":1000},"
                + "\"matching\":{\"fairness\":\"first-come\",\"maxTasksPerWindow\":null,\"windowMs\":null}}");

        WorkspaceStats stats = client().stats();

        assertNull(stats.getQueue().getOldestWaitingMs());
        assertTrue(stats.getQueue().getPerWorker().isEmpty());
        assertEquals("first-come", stats.getMatching().getFairness());
        assertNull(stats.getMatching().getMaxTasksPerWindow());
        assertNull(stats.getMatching().getWindowMs());
    }

    @Test
    void stats_from_a_data_plane_only_deployment_still_parse() throws Exception {
        // queue/matching are absent when the control plane is not attached.
        enqueueJson(200, "{\"plan\":\"free\",\"tasks\":{},\"workers\":0,\"meter\":{}}");

        WorkspaceStats stats = client().stats();

        assertNull(stats.getQueue());
        assertNull(stats.getMatching());
    }

    @Test
    void the_timeseries_reports_empty_buckets_as_gaps_not_zeros() throws Exception {
        // An hour with no completions has no average wait — reporting 0ms would be a lie.
        enqueueJson(200, "{\"from\":\"2026-07-23T00:00:00.000Z\",\"to\":\"2026-07-24T00:00:00.000Z\","
                + "\"bucket\":\"hour\",\"buckets\":["
                + "{\"bucketStart\":\"2026-07-23T00:00:00.000Z\",\"completed\":12,\"cancelled\":1,"
                + "\"avgWaitMs\":4200,\"p50WaitMs\":3000,\"p95WaitMs\":11000,\"avgHandleMs\":90000},"
                + "{\"bucketStart\":\"2026-07-23T01:00:00.000Z\",\"completed\":0,\"cancelled\":0,"
                + "\"avgWaitMs\":null,\"p50WaitMs\":null,\"p95WaitMs\":null,\"avgHandleMs\":null}]}");

        StatsTimeseriesResult result = client().history().timeseries(
                new StatsWindowQuery().from("2026-07-23T00:00:00.000Z")
                        .to("2026-07-24T00:00:00.000Z").bucket("hour"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/stats/timeseries", pathOf(request));
        assertTrue(queryOf(request).contains("bucket=hour"));
        assertEquals("2026-07-23T00:00:00.000Z", result.getFrom());
        assertEquals("2026-07-24T00:00:00.000Z", result.getTo());
        assertEquals("hour", result.getBucket());
        assertEquals("2026-07-23T00:00:00.000Z", result.getBuckets().get(0).getBucketStart());
        assertEquals(12, result.getBuckets().get(0).getCompleted());
        assertEquals(1, result.getBuckets().get(0).getCancelled());
        assertEquals(4200.0, result.getBuckets().get(0).getAvgWaitMs());
        assertEquals(3000.0, result.getBuckets().get(0).getP50WaitMs());
        assertEquals(11000.0, result.getBuckets().get(0).getP95WaitMs());
        assertEquals(90000.0, result.getBuckets().get(0).getAvgHandleMs());
        assertNull(result.getBuckets().get(1).getAvgWaitMs());
        assertNull(result.getBuckets().get(1).getAvgHandleMs());
    }

    @Test
    void asking_for_the_default_window_sends_no_parameters() throws Exception {
        enqueueJson(200, "{\"from\":\"a\",\"to\":\"b\",\"bucket\":\"hour\",\"buckets\":[]}");

        client().history().timeseries();

        assertEquals("/v1/stats/timeseries", takeRequest().getPath());
    }

    @Test
    void a_stats_window_exposes_the_bounds_it_was_built_with() {
        StatsWindowQuery query = new StatsWindowQuery().from("f").to("t").bucket("day");

        assertEquals("f", query.getFrom());
        assertEquals("t", query.getTo());
        assertEquals("day", query.getBucket());
    }

    @Test
    void worker_productivity_is_reported_per_worker_for_the_window() throws Exception {
        enqueueJson(200, "{\"from\":\"2026-07-23T00:00:00.000Z\",\"to\":\"2026-07-24T00:00:00.000Z\","
                + "\"workers\":[{\"workerId\":\"agent_1\",\"completed\":12,\"cancelled\":1,"
                + "\"avgWaitMs\":4200,\"avgHandleMs\":90000}]}");

        WorkerStatsResult result = client().history().workers(
                new StatsWindowQuery().from("2026-07-23T00:00:00.000Z"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/stats/workers", pathOf(request));
        assertEquals("2026-07-23T00:00:00.000Z", result.getFrom());
        assertEquals("2026-07-24T00:00:00.000Z", result.getTo());
        assertEquals("agent_1", result.getWorkers().get(0).getWorkerId());
        assertEquals(12, result.getWorkers().get(0).getCompleted());
        assertEquals(1, result.getWorkers().get(0).getCancelled());
        assertEquals(4200.0, result.getWorkers().get(0).getAvgWaitMs());
        assertEquals(90000.0, result.getWorkers().get(0).getAvgHandleMs());
    }

    @Test
    void worker_stats_for_the_default_window_send_no_parameters() throws Exception {
        enqueueJson(200, "{\"from\":\"a\",\"to\":\"b\",\"workers\":[]}");

        client().history().workers();

        assertEquals("/v1/stats/workers", takeRequest().getPath());
    }

    @Test
    void history_on_a_data_plane_only_deployment_is_reported_as_unavailable() {
        // 501 here is a deployment fact, not a bug — the error code has to say which.
        enqueueJson(501, "{\"error\":{\"code\":\"history_unavailable\","
                + "\"message\":\"historical stats require the control plane\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().history().timeseries());

        assertEquals("history_unavailable", error.getCode());
        assertEquals(501, error.getStatusCode());
    }

    @Test
    void the_supervisor_presence_view_separates_paused_from_on_break() throws Exception {
        // Paused is an operator action; on-break is the worker's own. Not the same thing.
        enqueueJson(200, "{\"workers\":["
                + "{\"workerId\":\"agent_1\",\"label\":\"Ada\",\"status\":\"working\"},"
                + "{\"workerId\":\"agent_2\",\"label\":\"Grace\",\"status\":\"on-break\","
                + "\"breakStartedAt\":\"2026-07-24T11:30:00.000Z\",\"breakReason\":\"lunch\"},"
                + "{\"workerId\":\"agent_3\",\"label\":\"Alan\",\"status\":\"paused\"}],"
                + "\"counts\":{\"working\":1,\"onBreak\":1,\"paused\":1,\"total\":3}}");

        TeamPresence presence = client().team().presence();

        assertEquals("/v1/team/presence", takeRequest().getPath());
        assertEquals(1, presence.getCounts().getWorking());
        assertEquals(1, presence.getCounts().getOnBreak());
        assertEquals(1, presence.getCounts().getPaused());
        assertEquals(3, presence.getCounts().getTotal());
        assertEquals("working", presence.getWorkers().get(0).getStatus());
        assertEquals("agent_1", presence.getWorkers().get(0).getWorkerId());
        assertNull(presence.getWorkers().get(0).getBreakStartedAt());
        assertEquals("lunch", presence.getWorkers().get(1).getBreakReason());
        assertEquals("2026-07-24T11:30:00.000Z", presence.getWorkers().get(1).getBreakStartedAt());
        assertEquals("Grace", presence.getWorkers().get(1).getLabel());
    }

    @Test
    void break_metrics_roll_up_per_worker_for_a_window() throws Exception {
        enqueueJson(200, "{\"workers\":[{\"workerId\":\"agent_2\",\"label\":\"Grace\",\"count\":2,"
                + "\"totalBreakMs\":2700000,\"longestBreakMs\":1800000,\"active\":true}],"
                + "\"totalBreakMs\":2700000,\"breakCount\":2,\"activeCount\":1}");

        WorkspaceBreakMetrics metrics = client().breaks()
                .metrics("2026-07-24T00:00:00.000Z", "2026-07-24T23:59:59.999Z");

        RecordedRequest request = takeRequest();
        assertTrue(queryOf(request).contains("from=2026-07-24T00"));
        assertEquals(1, metrics.getActiveCount());
        assertEquals(2, metrics.getBreakCount());
        assertEquals(2700000L, metrics.getTotalBreakMs());
        assertEquals("agent_2", metrics.getWorkers().get(0).getWorkerId());
        assertEquals("Grace", metrics.getWorkers().get(0).getLabel());
        assertEquals(2, metrics.getWorkers().get(0).getCount());
        assertEquals(1800000L, metrics.getWorkers().get(0).getLongestBreakMs());
        assertEquals(2700000L, metrics.getWorkers().get(0).getTotalBreakMs());
        assertTrue(metrics.getWorkers().get(0).isActive());
    }

    @Test
    void break_metrics_default_to_today() throws Exception {
        enqueueJson(200, "{\"workers\":[],\"totalBreakMs\":0,\"breakCount\":0,\"activeCount\":0}");

        assertEquals(0, client().breaks().metrics().getBreakCount());
        assertEquals("/v1/breaks/metrics", takeRequest().getPath());
    }

    // ---- the fields that had drifted off the Task model ----

    @Test
    void a_single_task_read_exposes_its_geo_constraints_and_rich_data_counts() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"tags\":[\"field\"],\"priority\":90,"
                + "\"status\":\"pending\",\"workerId\":\"agent_1\",\"createdAt\":1750000000000,"
                + "\"meta\":{\"ticket\":\"T-1\"},\"title\":\"Fix the meter\",\"latitude\":59.4,"
                + "\"longitude\":24.7,\"maxDistanceKm\":25,\"requireGeo\":true,"
                + "\"allowedCidrs\":[\"10.0.0.0/8\"],\"workflowRunId\":\"run_1\","
                + "\"workflowStepId\":\"collect\",\"data\":{\"hasContext\":true,\"referenceCount\":2,"
                + "\"attachmentCount\":1,\"commentCount\":3}}");

        Task task = client().tasks().get("task_8fk2");

        assertEquals("Fix the meter", task.getTitle());
        assertEquals(59.4, task.getLatitude());
        assertEquals(24.7, task.getLongitude());
        assertEquals(25.0, task.getMaxDistanceKm());
        assertEquals(Boolean.TRUE, task.getRequireGeo());
        assertEquals(List.of("10.0.0.0/8"), task.getAllowedCidrs());
        assertEquals("run_1", task.getWorkflowRunId());
        assertEquals("collect", task.getWorkflowStepId());
        assertTrue(task.getData().isHasContext());
        assertEquals(2, task.getData().getReferenceCount());
        assertEquals(1, task.getData().getAttachmentCount());
        assertEquals(3, task.getData().getCommentCount());
    }

    @Test
    void a_task_read_from_a_list_has_no_rich_data_summary() throws Exception {
        // `data` is populated on single reads only; a list entry must not fake an empty summary.
        enqueueJson(200, "{\"tasks\":[{\"id\":\"task_8fk2\",\"tags\":[],\"status\":\"queued\"}],"
                + "\"nextCursor\":null,\"hasMore\":false}");

        Task task = client().tasks().list(null).getTasks().get(0);

        assertNull(task.getData());
        assertNull(task.getTitle());
        assertNull(task.getWorkflowRunId());
    }

    @Test
    void the_skill_quotas_are_parsed_alongside_the_task_and_worker_ones() throws Exception {
        enqueueJson(200, "{\"skills\":[]}",
                "x-quota-skills-limit", "50", "x-quota-skills-remaining", "48",
                "x-quota-worker-skills-limit", "10", "x-quota-worker-skills-remaining", "7");
        Fivexer client = client();

        client.skills().list();

        assertEquals(50, client.getQuota().getSkillsLimit());
        assertEquals(48, client.getQuota().getSkillsRemaining());
        assertEquals(10, client.getQuota().getWorkerSkillsLimit());
        assertEquals(7, client.getQuota().getWorkerSkillsRemaining());
    }
}
