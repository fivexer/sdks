package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.BulkTaskReport;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.QueueAuditReport;
import io.fivexer.sdk.model.RevertedWeights;
import io.fivexer.sdk.model.SlaStats;
import io.fivexer.sdk.model.TaskCheckReport;
import io.fivexer.sdk.model.TaskEscalation;
import io.fivexer.sdk.model.TaskList;
import io.fivexer.sdk.model.UnparkTask;
import io.fivexer.sdk.model.WorkerPortalLink;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Bulk create, the dry-run check, the escalation ladder, and the parked/scheduled views.
 *
 * <p>This is the operational surface — reached for when a queue is misbehaving, not on the happy
 * path. Two things are pinned hard: bulk create reports partial success (so a caller reading only
 * the status code silently loses tasks), and unpark's reset flags decide whether the next sweep
 * parks the task straight back.
 */
class TaskOperationsTest extends MockServerBase {

    private static final String TASK = "{\"id\":\"task_8fk2\",\"status\":\"queued\","
            + "\"tags\":[\"english\"],\"priority\":90,\"createdAt\":1754000000000}";

    // ---- bulk create ----

    @Test
    void bulk_create_reports_partial_success_per_entry() throws Exception {
        // A 200 here does not mean everything was created. A caller that reads only the status
        // code loses the failures silently, so `failed` and the per-entry error must survive.
        enqueueJson(200, "{\"created\":1,\"failed\":1,\"results\":["
                + "{\"index\":0,\"id\":\"task_1\",\"status\":\"queued\"},"
                + "{\"index\":1,\"error\":{\"code\":\"validation_failed\",\"message\":\"tags required\"}}]}");

        // Both inputs are valid — CreateTask rejects empty tags locally, so a server-side
        // failure is the only kind bulk create can report, and the mock supplies it.
        BulkTaskReport report = client().tasks().createMany(
                List.of(new CreateTask(List.of("a")), new CreateTask(List.of("b"))));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/bulk", pathOf(request));
        assertEquals(2, bodyOf(request).getAsJsonArray("tasks").size());
        assertEquals(1, report.getCreated());
        assertEquals(1, report.getFailed());
        assertTrue(report.getResults().get(0).isOk());
        assertEquals("task_1", report.getResults().get(0).getId());
        assertFalse(report.getResults().get(1).isOk());
        assertEquals("validation_failed", report.getResults().get(1).getError().getCode());
    }

    @Test
    void a_bulk_entry_keeps_the_index_that_maps_it_back_to_the_input() throws Exception {
        // The caller's list is the only way to know *which* task failed; without index a partial
        // failure is unactionable.
        enqueueJson(200, "{\"created\":0,\"failed\":1,\"results\":["
                + "{\"index\":7,\"error\":{\"code\":\"plan_limit_exceeded\",\"message\":\"quota\"}}]}");

        BulkTaskReport report = client().tasks().createMany(List.of(new CreateTask(List.of("a"))));

        assertEquals(7, report.getResults().get(0).getIndex());
    }

    // ---- dry-run check ----

    @Test
    void check_reports_who_could_take_a_task_without_creating_it() throws Exception {
        enqueueJson(200, "{\"issues\":[{\"severity\":\"warning\",\"code\":\"no_coverage\","
                + "\"message\":\"nobody has welsh\",\"tag\":\"welsh\"}],\"eligibleWorkerCount\":0,"
                + "\"uncoveredTags\":[\"welsh\"],\"evaluatedAt\":1754000000000}");

        TaskCheckReport report = client().tasks().check(new CreateTask(List.of("welsh")));

        RecordedRequest request = takeRequest();
        assertEquals("POST", request.getMethod());
        assertEquals("/v1/tasks/check", pathOf(request));
        assertEquals(0, report.getEligibleWorkerCount());
        assertEquals(List.of("welsh"), report.getUncoveredTags());
        assertEquals("welsh", report.getIssues().get(0).getTag());
    }

    @Test
    void a_check_issue_without_a_tag_parses_as_null() throws Exception {
        enqueueJson(200, "{\"issues\":[{\"severity\":\"error\",\"code\":\"bad_sla\","
                + "\"message\":\"negative\"}],\"eligibleWorkerCount\":3,\"uncoveredTags\":[],"
                + "\"evaluatedAt\":1}");

        TaskCheckReport report = client().tasks().check(new CreateTask(List.of("a")));

        assertNull(report.getIssues().get(0).getTag());
    }

    // ---- ack / escalate / park ----

    @Test
    void ack_stops_the_response_clock_without_starting_work() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");

        client().tasks().ack("task_8fk2", "agent_1");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/ack", pathOf(request));
        assertEquals("agent_1", bodyOf(request).get("workerId").getAsString());
    }

    @Test
    void escalating_without_a_worker_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"escalated\":true,\"parked\":false,\"escalationLevel\":1}");

        TaskEscalation result = client().tasks().escalate("t1");

        assertEquals(0, bodyOf(takeRequest()).size());
        assertEquals(1, result.getEscalationLevel());
        assertFalse(result.getParked());
    }

    @Test
    void an_exhausted_ladder_reports_the_task_as_parked() throws Exception {
        // escalated:false + parked:true is the end of the ladder — the task left matching, and
        // this flag is the only thing that says so.
        enqueueJson(200, "{\"id\":\"t1\",\"escalated\":false,\"parked\":true,\"escalationLevel\":3}");

        TaskEscalation result = client().tasks().escalate("t1", "agent_1");

        assertEquals("agent_1", bodyOf(takeRequest()).get("workerId").getAsString());
        assertTrue(result.getParked());
    }

    @Test
    void parked_and_scheduled_are_separate_views_each_with_a_count() throws Exception {
        enqueueJson(200, "{\"tasks\":[" + TASK + "],\"count\":1}");
        enqueueJson(200, "{\"tasks\":[" + TASK + "],\"count\":2}");

        Fivexer client = client();
        TaskList parked = client.tasks().parked();
        TaskList scheduled = client.tasks().scheduled();

        assertEquals("/v1/tasks/parked", pathOf(takeRequest()));
        assertEquals("/v1/tasks/scheduled", pathOf(takeRequest()));
        assertEquals(1, parked.getCount());
        assertEquals(2, scheduled.getCount());
        assertEquals("task_8fk2", parked.getTasks().get(0).getId());
    }

    @Test
    void unparking_with_no_options_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"status\":\"queued\"}");

        client().tasks().unpark("t1");

        assertEquals(0, bodyOf(takeRequest()).size());
    }

    @Test
    void unpark_reset_flags_reach_the_wire_and_unset_ones_stay_absent() throws Exception {
        // Leave a clock set and the next sweep parks the task again — these flags are the whole
        // reason unpark is not just a status change. Absent differs from false.
        enqueueJson(200, "{\"id\":\"t1\",\"status\":\"queued\"}");

        client().tasks().unpark("t1", new UnparkTask().resetEscalation(true).resetSla(true));

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("resetEscalation").getAsBoolean());
        assertTrue(body.get("resetSla").getAsBoolean());
        assertFalse(body.has("resetSchedule"));
    }

    // ---- SLA stats and the queue audit ----

    @Test
    void sla_stats_default_to_the_whole_workspace() throws Exception {
        enqueueJson(200, "{\"tag\":null,\"offers\":10,\"acceptedInTime\":9}");

        SlaStats stats = client().slaStats();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/stats/sla", pathOf(request));
        assertEquals("", queryOf(request));
        assertNull(stats.getTag());
        assertEquals(10, stats.getOffers());
    }

    @Test
    void sla_stats_for_one_tag_send_it_as_a_query_param() throws Exception {
        enqueueJson(200, "{\"tag\":\"billing\",\"offers\":4,\"acceptanceRate\":0.75}");

        SlaStats stats = client().slaStats("billing");

        assertEquals("tag=billing", queryOf(takeRequest()));
        assertEquals(0.75, stats.getAcceptanceRate());
    }

    @Test
    void no_offers_yet_leaves_acceptance_rate_null_rather_than_zero() throws Exception {
        // 0.0 means "everyone missed"; null means "nothing measured". Collapsing them turns a
        // cold start into a false alarm on a dashboard.
        enqueueJson(200, "{\"tag\":null,\"offers\":0,\"acceptanceRate\":null}");

        assertNull(client().slaStats().getAcceptanceRate());
    }

    @Test
    void the_queue_audit_names_what_is_blocking_each_task() throws Exception {
        enqueueJson(200, "{\"evaluatedAt\":1754000000000,\"scanned\":2,\"entries\":["
                + "{\"taskId\":\"t1\",\"tags\":[\"welsh\"],\"waitingMs\":90000,"
                + "\"eligibleWorkerCount\":0,\"uncoveredTags\":[\"welsh\"],"
                + "\"blockers\":{\"backlog_full\":2,\"paused\":1}}],"
                + "\"sweepBacklog\":{\"scheduleActivations\":1,\"scheduleMisses\":0,"
                + "\"responseDeadlines\":4,\"completionDeadlines\":0,\"slaExpiries\":2}}");

        QueueAuditReport report = client().queueAudit(
                new QueueAuditQuery().limit(10).minWaitingMs(60000L).includeHealthy(false));

        String query = queryOf(takeRequest());
        assertTrue(query.contains("limit=10"), query);
        assertTrue(query.contains("minWaitingMs=60000"), query);
        // Fastify parses the string form; the lower-cased spelling is the contract.
        assertTrue(query.contains("includeHealthy=false"), query);
        assertEquals(2, report.getEntries().get(0).getBlockers().get("backlog_full"));
        assertEquals(4, report.getSweepBacklog().getResponseDeadlines());
    }

    @Test
    void a_never_queued_task_has_a_null_wait_rather_than_zero() throws Exception {
        enqueueJson(200, "{\"evaluatedAt\":1,\"scanned\":1,\"entries\":[{\"taskId\":\"t1\","
                + "\"tags\":[],\"waitingMs\":null,\"eligibleWorkerCount\":1,\"uncoveredTags\":[],"
                + "\"blockers\":{}}],\"sweepBacklog\":{}}");

        QueueAuditReport report = client().queueAudit();

        assertEquals("", queryOf(takeRequest()));
        assertNull(report.getEntries().get(0).getWaitingMs());
    }

    // ---- portal link, learning revert, skills.get ----

    @Test
    void the_portal_link_reports_a_disabled_portal_without_erroring() throws Exception {
        // A workspace with no published bundle is a normal state, not a failure.
        enqueueJson(200, "{\"portalEnabled\":false,\"portalUrl\":\"https://5xer.com/portal/ws_1/\","
                + "\"exists\":false,\"version\":null,\"template\":null,\"publishedAt\":null}");

        WorkerPortalLink link = client().portal();

        assertEquals("/v1/portal", pathOf(takeRequest()));
        assertFalse(link.getPortalEnabled());
        assertNull(link.getVersion());
    }

    @Test
    void reverting_learned_weights_without_ids_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"reverted\":[\"agent_1\"],\"count\":1}");

        RevertedWeights result = client().learning().revertWeights();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/learning/weights/revert", pathOf(request));
        assertEquals(0, bodyOf(request).size());
        assertEquals(1, result.getCount());
    }

    @Test
    void reverting_scoped_to_named_workers_sends_the_list() throws Exception {
        enqueueJson(200, "{\"reverted\":[\"agent_1\"],\"count\":1}");

        client().learning().revertWeights(List.of("agent_1", "agent_2"));

        assertEquals(2, bodyOf(takeRequest()).getAsJsonArray("workerIds").size());
    }

    @Test
    void fetching_one_skill_by_id() throws Exception {
        enqueueJson(200, "{\"id\":\"sk_1\",\"key\":\"welsh\",\"name\":\"Welsh\"}");

        assertEquals("welsh", client().skills().get("sk_1").getKey());
        assertEquals("/v1/skills/sk_1", pathOf(takeRequest()));
    }
}
