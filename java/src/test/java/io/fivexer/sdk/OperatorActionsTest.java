package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.AssignTaskResult;
import io.fivexer.sdk.model.PatchWorker;
import io.fivexer.sdk.model.RequiredSkill;
import io.fivexer.sdk.model.SuggestWorkers;
import io.fivexer.sdk.model.SuggestWorkersResult;
import io.fivexer.sdk.model.TaskPriority;
import io.fivexer.sdk.model.WorkerAvailability;
import io.fivexer.sdk.model.WorkerDetail;
import io.fivexer.sdk.model.WorkerSkillAssignment;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Operator overrides: assigning work by hand, repricing it, and taking workers off the line.
 *
 * <p>These bypass or reshape normal matching, so they are the operations most likely to be
 * reached for during an incident and the ones where a wrong request body does visible damage.
 */
class OperatorActionsTest extends MockServerBase {

    @Test
    void assigning_a_queued_task_reports_no_previous_worker() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"pending\",\"workerId\":\"agent_1\","
                + "\"previousWorkerId\":null}");

        AssignTaskResult result = client().tasks().assign("task_8fk2", "agent_1");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/assign", request.getPath());
        // The recorded body is a one-shot buffer, so read it once and assert against that.
        JsonObject body = bodyOf(request);
        assertEquals("agent_1", body.get("workerId").getAsString());
        assertFalse(body.has("force"));
        assertEquals("agent_1", result.getWorkerId());
        assertEquals("pending", result.getStatus());
        assertEquals("task_8fk2", result.getId());
        assertNull(result.getPreviousWorkerId());
    }

    @Test
    void reassigning_a_pending_task_names_the_worker_it_was_taken_from() throws Exception {
        // Knowing who lost the task is what lets an operator explain the move afterwards.
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"pending\",\"workerId\":\"agent_2\","
                + "\"previousWorkerId\":\"agent_1\"}");

        AssignTaskResult result = client().tasks().assign("task_8fk2", "agent_2");

        assertEquals("agent_1", result.getPreviousWorkerId());
    }

    @Test
    void forcing_an_assignment_sends_the_override_flag() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"pending\",\"workerId\":\"agent_1\"}");

        client().tasks().assign("task_8fk2", "agent_1", true);

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("force").getAsBoolean());
    }

    @Test
    void an_unforced_assignment_respects_the_backlog_cap() {
        enqueueJson(409, "{\"error\":{\"code\":\"worker_backlog_full\","
                + "\"message\":\"agent_1 is at its backlog limit\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().tasks().assign("task_8fk2", "agent_1"));

        assertEquals("worker_backlog_full", error.getCode());
        assertEquals(409, error.getStatusCode());
    }

    @Test
    void repricing_a_task_returns_its_new_priority() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"priority\":95}");

        TaskPriority result = client().tasks().setPriority("task_8fk2", 95);

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        assertEquals(95, bodyOf(request).get("priority").getAsInt());
        assertEquals(95.0, result.getPriority());
        assertEquals("task_8fk2", result.getId());
    }

    // ---- dry-run scoring ----

    @Test
    void suggesting_workers_explains_why_each_one_is_or_is_not_eligible() throws Exception {
        enqueueJson(200, "{\"tags\":[\"english\",\"billing\"],\"priority\":90,\"workers\":["
                + "{\"workerId\":\"agent_1\",\"eligible\":true,\"score\":180,\"effectivePriority\":90,"
                + "\"reasons\":[{\"tag\":\"english\",\"weight\":100}]},"
                + "{\"workerId\":\"agent_2\",\"eligible\":false,\"score\":0,\"effectivePriority\":90,"
                + "\"reasons\":[{\"vetoed\":true}]}]}");

        SuggestWorkersResult result = client().tasks().suggestWorkers(
                new SuggestWorkers(List.of("english", "billing"))
                        .priority(90.0)
                        .requiredSkills(List.of(new RequiredSkill("skl_1", 3)))
                        .limit(2));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/suggest-workers", request.getPath());
        assertEquals(List.of("english", "billing"), result.getTags());
        assertEquals(90.0, result.getPriority());
        assertTrue(result.getWorkers().get(0).isEligible());
        assertEquals(180.0, result.getWorkers().get(0).getScore());
        assertEquals(90.0, result.getWorkers().get(0).getEffectivePriority());
        assertEquals("agent_1", result.getWorkers().get(0).getWorkerId());
        assertEquals(100.0, result.getWorkers().get(0).getReasons().get(0).get("weight"));
        assertFalse(result.getWorkers().get(1).isEligible());
        assertEquals(0.0, result.getWorkers().get(1).getScore());
    }

    @Test
    void suggesting_workers_creates_no_task() throws Exception {
        // The whole point is to preview routing without side effects.
        enqueueJson(200, "{\"tags\":[],\"priority\":0,\"workers\":[]}");

        client().tasks().suggestWorkers(new SuggestWorkers(List.of("english")));

        assertEquals("/v1/tasks/suggest-workers", takeRequest().getPath());
        assertEquals(1, server.getRequestCount());
    }

    // ---- worker detail and availability ----

    @Test
    void reading_a_worker_shows_their_load_against_their_cap() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\",\"tags\":[\"english\",\"billing\"],"
                + "\"routingWeights\":{\"english\":100,\"billing\":50},"
                + "\"skills\":[{\"skillId\":\"skl_1\",\"key\":\"refunds\",\"name\":\"Refunds\","
                + "\"level\":4,\"weightOverride\":null,\"weight\":80}],"
                + "\"maxBacklogSize\":5,\"available\":true,\"queueDepth\":2}");

        WorkerDetail detail = client().workers().get("agent_1");

        assertEquals("/v1/workers/agent_1", takeRequest().getPath());
        assertEquals("agent_1", detail.getId());
        assertEquals(List.of("english", "billing"), detail.getTags());
        assertEquals(100.0, detail.getRoutingWeights().get("english"));
        assertEquals(2, detail.getQueueDepth());
        assertEquals(5, detail.getMaxBacklogSize());
        assertTrue(detail.isAvailable());
        assertEquals("Refunds", detail.getSkills().get(0).getName());
        assertEquals("refunds", detail.getSkills().get(0).getKey());
        assertEquals("skl_1", detail.getSkills().get(0).getSkillId());
        assertEquals(4, detail.getSkills().get(0).getLevel());
        assertEquals(80.0, detail.getSkills().get(0).getWeight());
        assertNull(detail.getSkills().get(0).getWeightOverride());
    }

    @Test
    void a_worker_with_no_skills_configured_is_distinguishable_from_one_with_none_assigned()
            throws Exception {
        // null means "skills not in use for this worker"; [] means "configured, currently empty".
        enqueueJson(200, "{\"id\":\"agent_1\",\"skills\":null,\"maxBacklogSize\":null}");

        WorkerDetail detail = client().workers().get("agent_1");

        assertNull(detail.getSkills());
        // Zero is a real cap meaning "receive nothing", so it must not collide with "unset".
        assertNull(detail.getMaxBacklogSize());
    }

    @Test
    void patching_a_worker_changes_only_the_fields_supplied() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        String workerId = client().workers().patch("agent_1",
                new PatchWorker().skills(List.of(new WorkerSkillAssignment("skl_1", 5))));

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        assertEquals(1, bodyOf(request).size());
        assertEquals("agent_1", workerId);
    }

    @Test
    void pausing_a_worker_keeps_their_backlog() throws Exception {
        // "Back in ten minutes": stop matching new work, but leave what they already hold.
        enqueueJson(200, "{\"id\":\"agent_1\",\"available\":false}");

        WorkerAvailability result = client().workers().setAvailability("agent_1", false);

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workers/agent_1/availability", request.getPath());
        JsonObject body = bodyOf(request);
        assertFalse(body.get("available").getAsBoolean());
        assertFalse(body.has("releaseBacklog"));
        assertFalse(result.isAvailable());
        assertEquals("agent_1", result.getId());
        assertNull(result.getReleasedTaskIds());
    }

    @Test
    void pausing_and_releasing_reports_which_tasks_were_requeued() throws Exception {
        // "Gone for the day": the unaccepted backlog goes back to the queue for others.
        enqueueJson(200, "{\"id\":\"agent_1\",\"available\":false,"
                + "\"releasedTaskIds\":[\"task_8fk2\",\"task_9aa3\"]}");

        WorkerAvailability result = client().workers().setAvailability("agent_1", false, true);

        assertTrue(bodyOf(takeRequest()).get("releaseBacklog").getAsBoolean());
        assertEquals(List.of("task_8fk2", "task_9aa3"), result.getReleasedTaskIds());
    }

    @Test
    void resuming_a_worker_never_mentions_backlog_release() throws Exception {
        // The API rejects releaseBacklog on resume, so the SDK must not send it either way.
        enqueueJson(200, "{\"id\":\"agent_1\",\"available\":true}");

        WorkerAvailability result = client().workers().setAvailability("agent_1", true);

        assertFalse(bodyOf(takeRequest()).has("releaseBacklog"));
        assertTrue(result.isAvailable());
    }

    @Test
    void asking_to_release_a_backlog_while_resuming_is_rejected_by_the_api() {
        enqueueJson(400, "{\"error\":{\"code\":\"invalid_body\","
                + "\"message\":\"releaseBacklog is only valid when pausing\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().workers().setAvailability("agent_1", true, true));

        assertEquals("invalid_body", error.getCode());
    }
}
