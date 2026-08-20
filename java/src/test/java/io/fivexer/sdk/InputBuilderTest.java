package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

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
 * Every fluent builder stores what it was handed.
 *
 * <p>These read as trivial, and each one individually is — but a builder setter that assigns the
 * wrong field is silent: the value simply never reaches the wire, and the API applies a default
 * instead of what the caller asked for. Reading each field back is the only thing that catches
 * a copy-paste slip across two dozen near-identical setters.
 */
class InputBuilderTest {

    @Test
    void a_task_builder_stores_every_routing_and_geo_constraint() {
        List<TaskReference> references = List.of(new TaskReference("https://crm/o/41"));
        CreateTask task = new CreateTask(List.of("english"))
                .id("task_1")
                .priority(90)
                .skillThresholds(Map.of("electrical", 3.0))
                .vetoedWorkers(List.of("agent_9"))
                .meta(Map.of("ticket", "T-1"))
                .title("Refund")
                .description("Order 41")
                .context(Map.of("orderId", "41"))
                .references(references)
                .latitude(59.4)
                .longitude(24.7)
                .maxDistanceKm(25)
                .requireGeo(true)
                .allowedCidrs(List.of("10.0.0.0/8"));

        assertEquals(List.of("english"), task.getTags());
        assertEquals("task_1", task.getId());
        assertEquals(90.0, task.getPriority());
        assertEquals(3.0, task.getSkillThresholds().get("electrical"));
        assertEquals(List.of("agent_9"), task.getVetoedWorkers());
        assertEquals("T-1", task.getMeta().get("ticket"));
        assertEquals("Refund", task.getTitle());
        assertEquals("Order 41", task.getDescription());
        assertEquals("41", task.getContext().get("orderId"));
        assertEquals(references, task.getReferences());
        assertEquals(59.4, task.getLatitude());
        assertEquals(24.7, task.getLongitude());
        assertEquals(25.0, task.getMaxDistanceKm());
        assertEquals(Boolean.TRUE, task.getRequireGeo());
        assertEquals(List.of("10.0.0.0/8"), task.getAllowedCidrs());
    }

    @Test
    void a_worker_builder_stores_identity_geo_and_capacity() {
        List<WorkerSkillAssignment> skills = List.of(new WorkerSkillAssignment("skl_1", 4));
        UpsertWorker worker = new UpsertWorker()
                .id("agent_1")
                .tags(List.of("english"))
                .routingWeights(Map.of("english", 100.0))
                .skills(skills)
                .ip("10.0.0.1")
                .latitude(59.4)
                .longitude(24.7)
                .maxTravelDistanceKm(25)
                .maxBacklogSize(5);

        assertEquals("agent_1", worker.getId());
        assertEquals(List.of("english"), worker.getTags());
        assertEquals(100.0, worker.getRoutingWeights().get("english"));
        assertEquals(skills, worker.getSkills());
        assertEquals("10.0.0.1", worker.getIp());
        assertEquals(59.4, worker.getLatitude());
        assertEquals(24.7, worker.getLongitude());
        assertEquals(25.0, worker.getMaxTravelDistanceKm());
        assertEquals(5, worker.getMaxBacklogSize());
    }

    @Test
    void a_worker_patch_stores_the_same_fields_minus_the_id() {
        List<WorkerSkillAssignment> skills = List.of(new WorkerSkillAssignment("skl_1", 4));
        PatchWorker patch = new PatchWorker()
                .tags(List.of("english"))
                .routingWeights(Map.of("english", 100.0))
                .skills(skills)
                .ip("10.0.0.1")
                .latitude(59.4)
                .longitude(24.7)
                .maxTravelDistanceKm(25.0)
                .maxBacklogSize(0);

        assertEquals(List.of("english"), patch.getTags());
        assertEquals(100.0, patch.getRoutingWeights().get("english"));
        assertEquals(skills, patch.getSkills());
        assertEquals("10.0.0.1", patch.getIp());
        assertEquals(59.4, patch.getLatitude());
        assertEquals(24.7, patch.getLongitude());
        assertEquals(25.0, patch.getMaxTravelDistanceKm());
        // Zero is a real cap meaning "receive nothing", distinct from "not set".
        assertEquals(0, patch.getMaxBacklogSize());
    }

    @Test
    void a_skill_assignment_stores_its_level_and_override() {
        WorkerSkillAssignment assignment = new WorkerSkillAssignment("skl_1", 4).weightOverride(80.0);

        assertEquals("skl_1", assignment.getSkillId());
        assertEquals(4, assignment.getLevel());
        assertEquals(80.0, assignment.getWeightOverride());
    }

    @Test
    void a_dry_run_request_stores_every_constraint_it_will_score_against() {
        List<RequiredSkill> required = List.of(new RequiredSkill("skl_1", 3));
        SuggestWorkers request = new SuggestWorkers(List.of("english"))
                .priority(90.0)
                .requiredSkills(required)
                .vetoedWorkers(List.of("agent_9"))
                .limit(5)
                .latitude(59.4)
                .longitude(24.7)
                .maxDistanceKm(25.0)
                .requireGeo(true)
                .allowedCidrs(List.of("10.0.0.0/8"));

        assertEquals(List.of("english"), request.getTags());
        assertEquals(90.0, request.getPriority());
        assertEquals(required, request.getRequiredSkills());
        assertEquals(List.of("agent_9"), request.getVetoedWorkers());
        assertEquals(5, request.getLimit());
        assertEquals(59.4, request.getLatitude());
        assertEquals(24.7, request.getLongitude());
        assertEquals(25.0, request.getMaxDistanceKm());
        assertEquals(Boolean.TRUE, request.getRequireGeo());
        assertEquals(List.of("10.0.0.0/8"), request.getAllowedCidrs());
        assertEquals("skl_1", required.get(0).getSkillId());
        assertEquals(3, required.get(0).getMinLevel());
    }

    @Test
    void a_context_update_stores_its_title_description_and_references() {
        List<TaskReference> references = List.of(
                new TaskReference("https://crm/o/41").label("Order 41").contentType("text/html"));
        SetTaskContext context = new SetTaskContext()
                .title("Refund")
                .description("Order 41")
                .context(Map.of("orderId", "41"))
                .references(references);

        assertEquals("Refund", context.getTitle());
        assertEquals("Order 41", context.getDescription());
        assertEquals("41", context.getContext().get("orderId"));
        assertEquals(references, context.getReferences());
        assertEquals("https://crm/o/41", references.get(0).getUrl());
        assertEquals("Order 41", references.get(0).getLabel());
        assertEquals("text/html", references.get(0).getContentType());
        // An input reference has no id until the API assigns one.
        assertNull(references.get(0).getId());
    }

    @Test
    void a_comment_stores_its_body_and_attribution() {
        AddComment comment = new AddComment("Called back").workerId("agent_1").authorLabel("Ada");

        assertEquals("Called back", comment.getBody());
        assertEquals("agent_1", comment.getWorkerId());
        assertEquals("Ada", comment.getAuthorLabel());
    }

    @Test
    void an_attachment_request_stores_its_file_facts() {
        CreateAttachment attachment =
                new CreateAttachment("a.pdf", "application/pdf", 10L).workerId("agent_1");

        assertEquals("a.pdf", attachment.getFilename());
        assertEquals("application/pdf", attachment.getContentType());
        assertEquals(10L, attachment.getSizeBytes());
        assertEquals("agent_1", attachment.getWorkerId());
    }

    @Test
    void a_skill_definition_and_its_patch_store_their_fields() {
        UpsertSkill skill = new UpsertSkill("refunds", "Refunds").description("Handles refunds");
        PatchSkill patch = new PatchSkill().name("Refunds & credits").description("Broader");

        assertEquals("refunds", skill.getKey());
        assertEquals("Refunds", skill.getName());
        assertEquals("Handles refunds", skill.getDescription());
        assertEquals("Refunds & credits", patch.getName());
        assertEquals("Broader", patch.getDescription());
    }

    @Test
    void a_run_start_stores_its_context_and_initiator() {
        StartRun start = new StartRun().context(Map.of("a", 1)).initiatorWorkerId("agent_1");

        assertEquals(1, start.getContext().get("a"));
        assertEquals("agent_1", start.getInitiatorWorkerId());
    }

    @Test
    void a_feedback_item_stores_signals_and_a_reward_independently() {
        LearningFeedbackItem item = new LearningFeedbackItem("t_1")
                .signals(Map.of("csat", 0.9))
                .reward(1.5);

        assertEquals("t_1", item.getTaskId());
        assertEquals(0.9, item.getSignals().get("csat"));
        assertEquals(1.5, item.getReward());
    }

    @Test
    void a_sequence_and_its_update_store_their_steps_and_filters() {
        List<NotificationSequenceStep> steps =
                List.of(new NotificationSequenceStep("matched", "task.expiring").offsetMs(1000));
        CreateNotificationSequence create =
                new CreateNotificationSequence("Escalate", steps).enabled(true).filterTags(List.of("billing"));
        UpdateNotificationSequence update = new UpdateNotificationSequence()
                .name("Escalate faster").enabled(false).steps(steps).filterTags(List.of("english"));

        assertEquals("Escalate", create.getName());
        assertEquals(steps, create.getSteps());
        assertEquals(Boolean.TRUE, create.getEnabled());
        assertEquals(List.of("billing"), create.getFilterTags());
        assertEquals("Escalate faster", update.getName());
        assertEquals(Boolean.FALSE, update.getEnabled());
        assertEquals(steps, update.getSteps());
        assertEquals(List.of("english"), update.getFilterTags());
        assertEquals("matched", steps.get(0).getTrigger());
        assertEquals("task.expiring", steps.get(0).getEventType());
        assertEquals(1000L, steps.get(0).getOffsetMs());
    }

    @Test
    void a_channel_and_its_update_store_their_delivery_settings() {
        CreateNotificationChannel create =
                new CreateNotificationChannel("webhook", "https://hooks", List.of("task.matched"))
                        .secret("whsec_abc");
        UpdateNotificationChannel update = new UpdateNotificationChannel()
                .type("websocket").target("wss://x").events(List.of("task.completed"))
                .secret("whsec_new").disabled(true);

        assertEquals("webhook", create.getType());
        assertEquals("https://hooks", create.getTarget());
        assertEquals(List.of("task.matched"), create.getEvents());
        assertEquals("whsec_abc", create.getSecret());
        assertEquals("websocket", update.getType());
        assertEquals("wss://x", update.getTarget());
        assertEquals(List.of("task.completed"), update.getEvents());
        assertEquals("whsec_new", update.getSecret());
        assertEquals(Boolean.TRUE, update.getDisabled());
    }

    @Test
    void a_worker_login_stores_all_three_credentials() {
        WorkerLogin login = new WorkerLogin("ws_1", "agent_1", "4821");

        assertEquals("ws_1", login.getWorkspaceId());
        assertEquals("agent_1", login.getWorkerId());
        assertEquals("4821", login.getPin());
    }

    @Test
    void a_workflow_definition_input_stores_its_graph_and_options() {
        List<WorkflowStep> steps = List.of(new WorkflowStep("a", "A")
                .routing(List.of(new WorkflowRouting("cond", "b"))));
        WorkflowDefinitionInput input = new WorkflowDefinitionInput("Onboarding", steps)
                .version(2)
                .initialStepId("a")
                .defaultTimeoutMs(600000L)
                .metadata(Map.of("owner", "ops"));

        assertEquals("Onboarding", input.getName());
        assertEquals(steps, input.getSteps());
        assertEquals(2, input.getVersion());
        assertEquals("a", input.getInitialStepId());
        assertEquals(600000L, input.getDefaultTimeoutMs());
        assertEquals("ops", input.getMetadata().get("owner"));
        assertEquals("cond", steps.get(0).getRouting().get(0).getCondition());
        assertEquals("b", steps.get(0).getRouting().get(0).getTargetStepId());
    }

    @Test
    void the_list_query_builders_expose_the_filters_they_hold() {
        ListTasksQuery tasks = new ListTasksQuery().status("queued").cursor("c1").limit(50);
        ListDecisionsQuery decisions = new ListDecisionsQuery().taskId("t").workerId("w").limit(5);
        ListRunsQuery runs = new ListRunsQuery().status("active").workflowId("wf").cursor("c").limit(9);

        assertEquals("queued", tasks.getStatus());
        assertEquals("c1", tasks.getCursor());
        assertEquals(50, tasks.getLimit());
        assertEquals("t", decisions.getTaskId());
        assertEquals("w", decisions.getWorkerId());
        assertEquals(5, decisions.getLimit());
        assertEquals("active", runs.getStatus());
        assertEquals("wf", runs.getWorkflowId());
        assertEquals("c", runs.getCursor());
        assertEquals(9, runs.getLimit());
    }

    @Test
    void an_unset_query_builds_no_parameters_at_all() {
        // A null map is what tells the transport to send no query string.
        assertNull(new ListTasksQuery().toQuery());
        assertNull(new ListDecisionsQuery().toQuery());
        assertNull(new ListRunsQuery().toQuery());
        assertNull(new StatsWindowQuery().toQuery());
    }

    @Test
    void a_run_query_builds_a_mutable_map_so_redundant_filters_can_be_dropped() {
        // workflows().listRuns() removes the workflowId it already has in the path.
        assertTrue(new ListRunsQuery().workflowId("wf").toQuery().containsKey("workflowId"));
        new ListRunsQuery().workflowId("wf").toQuery().remove("workflowId");
    }
}
