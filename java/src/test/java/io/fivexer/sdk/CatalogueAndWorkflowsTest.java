package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.PatchSkill;
import io.fivexer.sdk.model.Skill;
import io.fivexer.sdk.model.StartRun;
import io.fivexer.sdk.model.UpsertSkill;
import io.fivexer.sdk.model.WorkflowDefinition;
import io.fivexer.sdk.model.WorkflowDefinitionInput;
import io.fivexer.sdk.model.WorkflowDefinitionSummary;
import io.fivexer.sdk.model.WorkflowRun;
import io.fivexer.sdk.model.WorkflowRunPage;
import io.fivexer.sdk.model.WorkflowRunSteps;
import io.fivexer.sdk.model.WorkflowStep;
import java.util.List;
import java.util.Map;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/** The skill catalogue, workflow definitions, and the runs started from them. */
class CatalogueAndWorkflowsTest extends MockServerBase {

    private static final String SKILL_JSON = "{\"id\":\"skl_1\",\"key\":\"refunds\",\"name\":\"Refunds\","
            + "\"description\":\"Handles refund requests\",\"createdAt\":\"2026-07-24T10:00:00.000Z\"}";

    private static final String RUN_JSON = "{\"id\":\"run_1\",\"workflowId\":\"wf_onboard\","
            + "\"status\":\"active\",\"currentStepId\":\"collect\",\"currentTaskId\":\"task_8fk2\","
            + "\"initiatorWorkerId\":\"agent_1\",\"context\":{\"customerId\":\"c_1\"},"
            + "\"definitionVersion\":2,\"history\":[],\"parallelBranches\":null,"
            + "\"createdAt\":1750000000000,\"updatedAt\":1750000000000}";

    // ---- skills ----

    @Test
    void defining_a_skill_returns_the_stored_record() throws Exception {
        enqueueJson(201, SKILL_JSON);

        Skill skill = client().skills().create(
                new UpsertSkill("refunds", "Refunds").description("Handles refund requests"));

        assertEquals("/v1/skills", takeRequest().getPath());
        assertEquals("skl_1", skill.getId());
        assertEquals("refunds", skill.getKey());
        assertEquals("Refunds", skill.getName());
        assertEquals("Handles refund requests", skill.getDescription());
        assertEquals("2026-07-24T10:00:00.000Z", skill.getCreatedAt());
    }

    @Test
    void searching_the_catalogue_filters_by_query_and_limit() throws Exception {
        enqueueJson(200, "{\"skills\":[" + SKILL_JSON + "]}");

        List<Skill> skills = client().skills().list("ref", 10);

        RecordedRequest request = takeRequest();
        assertTrue(queryOf(request).contains("q=ref"));
        assertTrue(queryOf(request).contains("limit=10"));
        assertEquals(1, skills.size());
    }

    @Test
    void listing_the_whole_catalogue_sends_no_filters() throws Exception {
        enqueueJson(200, "{\"skills\":[]}");

        assertTrue(client().skills().list().isEmpty());
        assertEquals("/v1/skills", takeRequest().getPath());
    }

    @Test
    void renaming_a_skill_leaves_its_key_untouched() throws Exception {
        // The key is the stable identifier workers are matched on; only the label changes.
        enqueueJson(200, SKILL_JSON);

        Skill skill = client().skills().patch("skl_1", new PatchSkill().name("Refunds & credits"));

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        assertFalse(bodyOf(request).has("key"));
        assertEquals("refunds", skill.getKey());
    }

    @Test
    void removing_a_skill_returns_nothing() throws Exception {
        enqueueEmpty(204);

        client().skills().remove("skl_1");

        assertEquals("/v1/skills/skl_1", takeRequest().getPath());
    }

    @Test
    void suggesting_companions_joins_the_selected_list_into_one_parameter() throws Exception {
        enqueueJson(200, "{\"skills\":[" + SKILL_JSON + "]}");

        client().skills().suggest(List.of("billing", "english"), 5);

        String query = queryOf(takeRequest());
        assertTrue(query.contains("selected=billing%2Cenglish") || query.contains("selected=billing,english"),
                "expected a comma-joined selected parameter, got: " + query);
    }

    @Test
    void suggesting_with_nothing_chosen_yet_omits_the_selected_parameter() throws Exception {
        enqueueJson(200, "{\"skills\":[]}");

        assertTrue(client().skills().suggest().isEmpty());
        assertEquals("/v1/skills/suggest", takeRequest().getPath());
    }

    @Test
    void an_empty_selection_omits_the_parameter_too() throws Exception {
        enqueueJson(200, "{\"skills\":[]}");

        client().skills().suggest(List.of(), null);

        assertEquals("/v1/skills/suggest", takeRequest().getPath());
    }

    // ---- workflow definitions ----

    @Test
    void listing_definitions_returns_id_and_name_only() throws Exception {
        enqueueJson(200, "{\"workflows\":[{\"id\":\"wf_onboard\",\"name\":\"Onboarding\"}]}");

        List<WorkflowDefinitionSummary> workflows = client().workflows().list();

        assertEquals("wf_onboard", workflows.get(0).getId());
        assertEquals("Onboarding", workflows.get(0).getName());
    }

    @Test
    void listing_definitions_in_an_empty_workspace_returns_an_empty_list() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().workflows().list().isEmpty());
    }

    @Test
    void reading_a_definition_preserves_which_step_ends_the_workflow() throws Exception {
        // `defaultNextStepId: null` terminates a workflow and must survive the round trip;
        // an absent key means "no fallback declared", which is a different thing.
        enqueueJson(200, "{\"id\":\"wf_onboard\",\"name\":\"Onboarding\",\"version\":2,"
                + "\"initialStepId\":\"collect\",\"defaultTimeoutMs\":600000,\"steps\":["
                + "{\"id\":\"collect\",\"name\":\"Collect docs\",\"taskType\":\"assignment\","
                + "\"defaultNextStepId\":\"review\"},"
                + "{\"id\":\"review\",\"name\":\"Review\",\"taskType\":\"external\","
                + "\"external\":{\"name\":\"compliance\"},\"timeoutMs\":300000,"
                + "\"defaultNextStepId\":null}]}");

        WorkflowDefinition definition = client().workflows().get("wf_onboard");

        assertEquals(2, definition.getVersion());
        assertEquals("collect", definition.getInitialStepId());
        assertEquals("wf_onboard", definition.getId());
        assertEquals("Onboarding", definition.getName());
        assertEquals(600000L, definition.getDefaultTimeoutMs());
        assertNull(definition.getMetadata());
        assertFalse(definition.getSteps().get(0).isTerminal());
        assertEquals("review", definition.getSteps().get(0).getDefaultNextStepId());
        assertTrue(definition.getSteps().get(1).isTerminal());
        assertEquals(300000L, definition.getSteps().get(1).getTimeoutMs());
    }

    @Test
    void saving_a_definition_puts_it_at_the_url_that_names_it() throws Exception {
        enqueueJson(200, "{\"id\":\"wf_onboard\",\"name\":\"Onboarding\",\"version\":1,"
                + "\"initialStepId\":\"collect\",\"steps\":[]}");

        client().workflows().save("wf_onboard", new WorkflowDefinitionInput("Onboarding",
                List.of(new WorkflowStep("collect", "Collect docs").taskType("assignment")))
                .initialStepId("collect"));

        RecordedRequest request = takeRequest();
        assertEquals("PUT", request.getMethod());
        assertEquals("/v1/workflows/wf_onboard", request.getPath());
        assertFalse(bodyOf(request).has("id"));
    }

    @Test
    void saving_an_unreachable_graph_is_rejected_by_the_engine() {
        enqueueJson(400, "{\"error\":{\"code\":\"invalid_workflow\","
                + "\"message\":\"initial step 'missing' is not defined\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> client().workflows()
                .save("wf_broken", new WorkflowDefinitionInput("Broken", List.of())));

        assertEquals("invalid_workflow", error.getCode());
        assertEquals(400, error.getStatusCode());
    }

    @Test
    void deleting_a_definition_returns_nothing() throws Exception {
        enqueueEmpty(204);

        client().workflows().remove("wf_onboard");

        assertEquals("DELETE", takeRequest().getMethod());
    }

    // ---- runs ----

    @Test
    void starting_a_run_returns_the_first_active_step() throws Exception {
        enqueueJson(201, RUN_JSON);

        WorkflowRun run = client().workflows().run("wf_onboard",
                new StartRun().context(Map.of("customerId", "c_1")).initiatorWorkerId("agent_1"));

        assertEquals("/v1/workflows/wf_onboard/runs", takeRequest().getPath());
        assertEquals("active", run.getStatus());
        assertEquals("collect", run.getCurrentStepId());
        assertEquals("task_8fk2", run.getCurrentTaskId());
        assertEquals("agent_1", run.getInitiatorWorkerId());
        assertEquals("run_1", run.getId());
        assertEquals("wf_onboard", run.getWorkflowId());
        assertEquals(2, run.getDefinitionVersion());
        assertEquals("c_1", run.getContext().get("customerId"));
        assertTrue(run.getHistory().isEmpty());
        assertNull(run.getParallelBranches());
        assertEquals(1750000000000L, run.getCreatedAt());
        assertEquals(1750000000000L, run.getUpdatedAt());
    }

    @Test
    void starting_a_run_with_no_input_still_posts_a_body() throws Exception {
        enqueueJson(201, RUN_JSON);

        client().workflows().run("wf_onboard");

        assertEquals(0, bodyOf(takeRequest()).size());
    }

    @Test
    void listing_one_definitions_runs_drops_the_redundant_workflow_filter() throws Exception {
        // The workflow id is already in the path; repeating it as a filter would be noise.
        enqueueJson(200, "{\"runs\":[" + RUN_JSON + "],\"nextCursor\":null}");

        WorkflowRunPage page = client().workflows().listRuns("wf_onboard",
                new ListRunsQuery().workflowId("wf_other").status("active"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workflows/wf_onboard/runs", pathOf(request));
        assertEquals("status=active", queryOf(request));
        assertNull(page.getNextCursor());
        assertEquals(1, page.getRuns().size());
    }

    @Test
    void listing_one_definitions_runs_with_only_the_redundant_filter_sends_no_query()
            throws Exception {
        enqueueJson(200, "{\"runs\":[]}");

        client().workflows().listRuns("wf_onboard", new ListRunsQuery().workflowId("wf_onboard"));

        assertEquals("/v1/workflows/wf_onboard/runs", takeRequest().getPath());
    }

    @Test
    void listing_one_definitions_runs_unfiltered_sends_no_query() throws Exception {
        enqueueJson(200, "{\"runs\":[]}");

        client().workflows().listRuns("wf_onboard");

        assertEquals("/v1/workflows/wf_onboard/runs", takeRequest().getPath());
    }

    @Test
    void listing_runs_across_the_workspace_keeps_the_definition_filter() throws Exception {
        enqueueJson(200, "{\"runs\":[],\"nextCursor\":\"cursor_r1\"}");

        WorkflowRunPage page = client().runs().list(
                new ListRunsQuery().workflowId("wf_onboard").status("completed").cursor("c").limit(5));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workflow-runs", pathOf(request));
        assertTrue(queryOf(request).contains("workflowId=wf_onboard"));
        assertEquals("cursor_r1", page.getNextCursor());
    }

    @Test
    void listing_all_runs_unfiltered_sends_no_query() throws Exception {
        enqueueJson(200, "{\"runs\":[]}");

        client().runs().list();

        assertEquals("/v1/workflow-runs", takeRequest().getPath());
    }

    @Test
    void reading_a_run_returns_the_steps_it_has_completed() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"workflowId\":\"wf_1\",\"status\":\"completed\","
                + "\"history\":[{\"stepId\":\"collect\",\"taskId\":\"task_8fk2\",\"workerId\":\"agent_1\","
                + "\"completedAt\":1750000500000,\"result\":{\"ok\":true}}],"
                + "\"parallelBranches\":[{\"stepId\":\"a\",\"assignmentId\":\"t_1\",\"status\":\"pending\"}]}");

        WorkflowRun run = client().runs().get("run_1");

        assertEquals("completed", run.getStatus());
        assertEquals("agent_1", run.getHistory().get(0).getWorkerId());
        assertEquals("collect", run.getHistory().get(0).getStepId());
        assertEquals("task_8fk2", run.getHistory().get(0).getTaskId());
        assertEquals(1750000500000L, run.getHistory().get(0).getCompletedAt());
        assertEquals(true, run.getHistory().get(0).getResult().get("ok"));
        assertEquals("a", run.getParallelBranches().get(0).getStepId());
        assertEquals("t_1", run.getParallelBranches().get(0).getAssignmentId());
        assertEquals("pending", run.getParallelBranches().get(0).getStatus());
        assertNull(run.getParallelBranches().get(0).getResult());
    }

    @Test
    void the_step_view_reports_which_step_is_waiting_on_a_callback() throws Exception {
        enqueueJson(200, "{\"runId\":\"run_1\",\"status\":\"active\",\"steps\":["
                + "{\"stepId\":\"collect\",\"name\":\"Collect docs\",\"taskType\":\"assignment\","
                + "\"state\":\"completed\",\"taskId\":\"task_8fk2\",\"workerId\":\"agent_1\","
                + "\"completedAt\":1,\"result\":{\"ok\":true}},"
                + "{\"stepId\":\"review\",\"name\":\"Review\",\"taskType\":\"external\","
                + "\"state\":\"awaiting_callback\",\"taskId\":null,\"workerId\":null,"
                + "\"completedAt\":null,\"result\":null}]}");

        WorkflowRunSteps view = client().runs().steps("run_1");

        assertEquals("run_1", view.getRunId());
        assertEquals("active", view.getStatus());
        assertEquals("completed", view.getSteps().get(0).getState());
        assertEquals("Collect docs", view.getSteps().get(0).getName());
        assertEquals("assignment", view.getSteps().get(0).getTaskType());
        assertEquals("task_8fk2", view.getSteps().get(0).getTaskId());
        assertEquals("agent_1", view.getSteps().get(0).getWorkerId());
        assertEquals(1L, view.getSteps().get(0).getCompletedAt());
        assertEquals(true, view.getSteps().get(0).getResult().get("ok"));
        assertEquals("awaiting_callback", view.getSteps().get(1).getState());
        assertNull(view.getSteps().get(1).getTaskId());
        assertNull(view.getSteps().get(1).getCompletedAt());
    }

    @Test
    void cancelling_a_run_stops_it() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"status\":\"cancelled\"}");

        WorkflowRun run = client().runs().cancel("run_1");

        RecordedRequest request = takeRequest();
        assertEquals("POST", request.getMethod());
        assertEquals("/v1/workflow-runs/run_1/cancel", request.getPath());
        assertEquals("cancelled", run.getStatus());
    }

    @Test
    void completing_a_callback_step_advances_the_run() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"status\":\"active\",\"currentStepId\":\"notify\"}");

        WorkflowRun run = client().runs().completeStep("run_1", "review", Map.of("approved", true));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workflow-runs/run_1/steps/review/complete", request.getPath());
        assertTrue(bodyOf(request).getAsJsonObject("data").get("approved").getAsBoolean());
        assertEquals("notify", run.getCurrentStepId());
    }

    @Test
    void completing_a_callback_step_without_data_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"status\":\"active\"}");

        client().runs().completeStep("run_1", "review");

        assertEquals(0, bodyOf(takeRequest()).size());
    }

    @Test
    void failing_a_callback_step_fails_the_run() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"status\":\"failed\"}");

        WorkflowRun run = client().runs().failStep("run_1", "review", "compliance unreachable");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workflow-runs/run_1/steps/review/fail", request.getPath());
        assertEquals("compliance unreachable", bodyOf(request).get("error").getAsString());
        assertEquals("failed", run.getStatus());
    }

    @Test
    void failing_a_callback_step_without_a_reason_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"id\":\"run_1\",\"status\":\"failed\"}");

        client().runs().failStep("run_1", "review");

        assertEquals(0, bodyOf(takeRequest()).size());
    }
}
