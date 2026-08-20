package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.fivexer.sdk.model.AddComment;
import io.fivexer.sdk.model.CreateAttachment;
import io.fivexer.sdk.model.CreateNotificationChannel;
import io.fivexer.sdk.model.CreateNotificationSequence;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.LearningFeedbackItem;
import io.fivexer.sdk.model.NotificationSequenceStep;
import io.fivexer.sdk.model.PatchSkill;
import io.fivexer.sdk.model.PatchWorker;
import io.fivexer.sdk.model.RequiredSkill;
import io.fivexer.sdk.model.SetTaskContext;
import io.fivexer.sdk.model.StartRun;
import io.fivexer.sdk.model.SuggestWorkers;
import io.fivexer.sdk.model.TaskReference;
import io.fivexer.sdk.model.UpdateNotificationChannel;
import io.fivexer.sdk.model.UpdateNotificationSequence;
import io.fivexer.sdk.model.UpsertSkill;
import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerLogin;
import io.fivexer.sdk.model.WorkerSkillAssignment;
import io.fivexer.sdk.model.WorkflowDefinitionInput;
import io.fivexer.sdk.model.WorkflowRouting;
import io.fivexer.sdk.model.WorkflowStep;
import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.Test;

/**
 * How input models serialise onto the wire.
 *
 * <p>The rule these all turn on: an optional field that was never set must be <em>absent</em>,
 * never {@code null}. Every partial-update endpoint keeps the stored value for an absent field,
 * so a stray null silently wipes data. Driving the models directly keeps these checks free of
 * HTTP and makes them the fastest tests in the suite.
 */
class RequestBuildingTest {

    private static JsonObject parse(String json) {
        return JsonParser.parseString(json).getAsJsonObject();
    }

    @Test
    void a_minimal_task_sends_only_its_tags() {
        JsonObject body = parse(new CreateTask(List.of("english")).toJson());

        assertEquals(1, body.size());
        assertEquals("english", body.getAsJsonArray("tags").get(0).getAsString());
    }

    @Test
    void a_task_carries_geo_cidr_and_rich_data_together() {
        JsonObject body = parse(new CreateTask(List.of("field"))
                .priority(90)
                .title("Fix the meter")
                .description("Meter 41 is stuck")
                .context(Map.of("meterId", "41"))
                .latitude(59.4)
                .longitude(24.7)
                .maxDistanceKm(25)
                .requireGeo(true)
                .allowedCidrs(List.of("10.0.0.0/8"))
                .references(List.of(new TaskReference("https://crm/o/41").label("Order 41")))
                .toJson());

        assertEquals(90, body.get("priority").getAsInt());
        assertEquals("Fix the meter", body.get("title").getAsString());
        assertEquals(24.7, body.get("longitude").getAsDouble());
        assertTrue(body.get("requireGeo").getAsBoolean());
        assertEquals("10.0.0.0/8", body.getAsJsonArray("allowedCidrs").get(0).getAsString());
        JsonObject reference = body.getAsJsonArray("references").get(0).getAsJsonObject();
        assertEquals("Order 41", reference.get("label").getAsString());
        // The API assigns reference ids, so an input reference must not invent one.
        assertFalse(reference.has("id"));
    }

    @Test
    void a_task_requires_at_least_one_tag() {
        assertThrows(IllegalArgumentException.class, () -> new CreateTask(null));
        assertThrows(IllegalArgumentException.class, () -> new CreateTask(List.of()));
    }

    @Test
    void an_empty_worker_patch_sends_an_empty_body_rather_than_nulls() {
        // A body of nulls would clear tags, weights and skills instead of leaving them alone.
        assertEquals(0, parse(new PatchWorker().toJson()).size());
    }

    @Test
    void patching_one_worker_field_leaves_the_others_absent() {
        JsonObject body = parse(new PatchWorker().maxBacklogSize(3).toJson());

        assertEquals(1, body.size());
        assertEquals(3, body.get("maxBacklogSize").getAsInt());
    }

    @Test
    void upserting_a_worker_serialises_skill_assignments() {
        JsonObject body = parse(new UpsertWorker("agent_1")
                .skills(List.of(new WorkerSkillAssignment("skl_1", 4)))
                .maxBacklogSize(5)
                .toJson());

        JsonObject skill = body.getAsJsonArray("skills").get(0).getAsJsonObject();
        assertEquals("skl_1", skill.get("skillId").getAsString());
        assertEquals(4, skill.get("level").getAsInt());
        assertFalse(skill.has("weightOverride"));
    }

    @Test
    void a_skill_weight_override_is_sent_only_when_set() {
        JsonObject body = parse(new WorkerSkillAssignment("skl_1", 3).weightOverride(80.0).toJson());

        assertEquals(80.0, body.get("weightOverride").getAsDouble());
    }

    @Test
    void an_empty_worker_upsert_asks_the_api_to_generate_an_id() {
        assertEquals(0, parse(new UpsertWorker().toJson()).size());
    }

    @Test
    void an_empty_context_update_sends_an_empty_body() {
        assertEquals(0, parse(new SetTaskContext().toJson()).size());
    }

    @Test
    void setting_context_serialises_references_without_ids() {
        JsonObject body = parse(new SetTaskContext()
                .title("Refund")
                .references(List.of(new TaskReference("https://crm/o/41")))
                .toJson());

        assertEquals("Refund", body.get("title").getAsString());
        assertFalse(body.has("description"));
        assertFalse(body.getAsJsonArray("references").get(0).getAsJsonObject().has("id"));
    }

    @Test
    void a_comment_without_attribution_sends_only_the_body() {
        JsonObject body = parse(new AddComment("Called back").toJson());

        assertEquals(1, body.size());
        assertEquals("Called back", body.get("body").getAsString());
    }

    @Test
    void a_comment_attributed_to_a_worker_carries_the_worker_id() {
        JsonObject body = parse(new AddComment("Called back").workerId("agent_1").toJson());

        assertEquals("agent_1", body.get("workerId").getAsString());
    }

    @Test
    void an_attachment_without_worker_attribution_omits_the_worker_id() {
        JsonObject body = parse(new CreateAttachment("a.pdf", "application/pdf", 10L).toJson());

        assertEquals(3, body.size());
        assertEquals(10, body.get("sizeBytes").getAsInt());
    }

    @Test
    void a_skill_without_a_description_omits_it() {
        JsonObject body = parse(new UpsertSkill("refunds", "Refunds").toJson());

        assertEquals(2, body.size());
    }

    @Test
    void an_empty_skill_patch_sends_an_empty_body() {
        assertEquals(0, parse(new PatchSkill().toJson()).size());
    }

    @Test
    void starting_a_run_without_context_sends_an_empty_body() {
        assertEquals(0, parse(new StartRun().toJson()).size());
    }

    @Test
    void starting_a_run_carries_context_and_initiator() {
        JsonObject body = parse(new StartRun()
                .context(Map.of("customerId", "c_1"))
                .initiatorWorkerId("agent_1")
                .toJson());

        assertEquals("agent_1", body.get("initiatorWorkerId").getAsString());
        assertEquals("c_1", body.getAsJsonObject("context").get("customerId").getAsString());
    }

    @Test
    void bulk_feedback_serialises_signals_and_rewards_independently() {
        JsonObject withReward = parse(new LearningFeedbackItem("t_1").reward(1.0).toJson());
        JsonObject withSignals = parse(new LearningFeedbackItem("t_2")
                .signals(Map.of("csat", 0.9)).toJson());

        assertFalse(withReward.has("signals"));
        assertFalse(withSignals.has("reward"));
        assertEquals(0.9, withSignals.getAsJsonObject("signals").get("csat").getAsDouble());
    }

    @Test
    void suggesting_workers_serialises_required_skill_levels() {
        JsonObject body = parse(new SuggestWorkers(List.of("english"))
                .requiredSkills(List.of(new RequiredSkill("skl_1", 3)))
                .limit(2)
                .toJson());

        JsonObject required = body.getAsJsonArray("requiredSkills").get(0).getAsJsonObject();
        assertEquals("skl_1", required.get("skillId").getAsString());
        assertEquals(3, required.get("minLevel").getAsInt());
        assertFalse(body.has("vetoedWorkers"));
    }

    @Test
    void a_sequence_step_offset_is_sent_only_when_given() {
        JsonObject without = parse(new NotificationSequenceStep("matched", "task.expiring").toJson());
        JsonObject with = parse(new NotificationSequenceStep("matched", "task.expiring")
                .offsetMs(300000).toJson());

        assertFalse(without.has("offsetMs"));
        assertEquals(300000, with.get("offsetMs").getAsInt());
    }

    @Test
    void creating_a_sequence_without_options_sends_name_and_steps_only() {
        JsonObject body = parse(new CreateNotificationSequence("Escalate", List.of()).toJson());

        assertEquals(2, body.size());
    }

    @Test
    void an_empty_sequence_update_sends_an_empty_body() {
        assertEquals(0, parse(new UpdateNotificationSequence().toJson()).size());
    }

    @Test
    void creating_a_channel_without_a_secret_omits_it() {
        JsonObject body = parse(new CreateNotificationChannel("webhook", "https://hooks", List.of("task.matched"))
                .toJson());

        assertEquals(3, body.size());
        assertFalse(body.has("secret"));
    }

    @Test
    void an_empty_channel_update_sends_an_empty_body() {
        assertEquals(0, parse(new UpdateNotificationChannel().toJson()).size());
    }

    @Test
    void disabling_a_channel_touches_only_the_disabled_flag() {
        JsonObject body = parse(new UpdateNotificationChannel().disabled(true).toJson());

        assertEquals(1, body.size());
        assertTrue(body.get("disabled").getAsBoolean());
    }

    @Test
    void a_login_always_sends_all_three_credentials() {
        JsonObject body = parse(new WorkerLogin("ws_1", "agent_1", "4821").toJson());

        assertEquals("ws_1", body.get("workspaceId").getAsString());
        assertEquals("agent_1", body.get("workerId").getAsString());
        assertEquals("4821", body.get("pin").getAsString());
    }

    @Test
    void required_input_fields_are_rejected_when_missing() {
        assertThrows(IllegalArgumentException.class, () -> new AddComment(null));
        assertThrows(IllegalArgumentException.class, () -> new UpsertSkill(null, "n"));
        assertThrows(IllegalArgumentException.class, () -> new WorkerLogin("ws", null, "pin"));
        assertThrows(IllegalArgumentException.class, () -> new WorkflowDefinitionInput(null, List.of()));
        assertThrows(IllegalArgumentException.class, () -> new WorkflowDefinitionInput("n", null));
        assertThrows(IllegalArgumentException.class, () -> new TaskReference(null));
    }

    // ---- workflow definitions: the tri-state successor ----

    @Test
    void a_terminal_step_serialises_an_explicit_null_successor() {
        // `defaultNextStepId: null` is what ends a workflow — it must survive null-pruning.
        JsonObject step = new WorkflowStep("done", "Done").taskType("assignment").endWorkflow().toJsonTree();

        assertTrue(step.has("defaultNextStepId"));
        assertTrue(step.get("defaultNextStepId").isJsonNull());
    }

    @Test
    void a_step_with_no_declared_successor_omits_the_key_entirely() {
        JsonObject step = new WorkflowStep("a", "A").toJsonTree();

        assertFalse(step.has("defaultNextStepId"));
        assertEquals(2, step.size());
    }

    @Test
    void naming_a_successor_after_marking_a_step_terminal_clears_the_terminal_flag() {
        WorkflowStep step = new WorkflowStep("a", "A").endWorkflow().defaultNextStepId("b");

        assertFalse(step.isTerminal());
        assertEquals("b", step.toJsonTree().get("defaultNextStepId").getAsString());
    }

    @Test
    void saving_a_definition_serialises_routing_rules_in_order() {
        JsonObject body = parse(new WorkflowDefinitionInput("Onboarding", List.of(
                new WorkflowStep("collect", "Collect")
                        .taskType("assignment")
                        .routing(List.of(
                                new WorkflowRouting("result.ok === true", "review"),
                                new WorkflowRouting("true", "reject")))))
                .initialStepId("collect")
                .toJson());

        JsonObject step = body.getAsJsonArray("steps").get(0).getAsJsonObject();
        assertEquals("review", step.getAsJsonArray("routing").get(0).getAsJsonObject()
                .get("targetStepId").getAsString());
        assertEquals("reject", step.getAsJsonArray("routing").get(1).getAsJsonObject()
                .get("targetStepId").getAsString());
    }

    @Test
    void saving_a_definition_never_puts_the_id_in_the_body() {
        // The URL is the id of record; a body id would be ambiguous.
        JsonObject body = parse(new WorkflowDefinitionInput("N", List.of()).toJson());

        assertFalse(body.has("id"));
    }

    @Test
    void a_definition_carries_its_optional_version_and_timeout_when_set() {
        JsonObject body = parse(new WorkflowDefinitionInput("N", List.of(new WorkflowStep("a", "A")))
                .version(3)
                .defaultTimeoutMs(600000L)
                .metadata(Map.of("owner", "ops"))
                .toJson());

        assertEquals(3, body.get("version").getAsInt());
        assertEquals(600000L, body.get("defaultTimeoutMs").getAsLong());
        assertEquals("ops", body.getAsJsonObject("metadata").get("owner").getAsString());
    }

    @Test
    void a_step_exposes_the_full_execution_policy_it_was_built_with() {
        WorkflowStep step = new WorkflowStep("review", "Review")
                .taskType("external")
                .external(Map.of("name", "compliance"))
                .machineTask(Map.of("handler", "h"))
                .assignmentTemplate(Map.of("tags", List.of("english")))
                .targetUser("initiator")
                .parallelStepIds(List.of("a", "b"))
                .waitForAll(true)
                .failurePolicy("retry")
                .maxRetries(3)
                .timeoutMs(300000L);

        assertEquals("external", step.getTaskType());
        assertEquals("compliance", step.getExternal().get("name"));
        assertEquals("h", step.getMachineTask().get("handler"));
        assertEquals("initiator", step.getTargetUser());
        assertEquals(List.of("a", "b"), step.getParallelStepIds());
        assertEquals(Boolean.TRUE, step.getWaitForAll());
        assertEquals("retry", step.getFailurePolicy());
        assertEquals(3, step.getMaxRetries());
        assertEquals(300000L, step.getTimeoutMs());
        assertEquals("review", step.getId());
        assertEquals("Review", step.getName());
        assertTrue(step.getAssignmentTemplate().containsKey("tags"));
    }
}
