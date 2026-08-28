package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.EscalationPolicy;
import io.fivexer.sdk.model.RecurrencePolicy;
import io.fivexer.sdk.model.RequiredSkill;
import io.fivexer.sdk.model.SchedulePolicy;
import io.fivexer.sdk.model.SlaPolicy;
import io.fivexer.sdk.model.Task;
import java.util.List;
import org.junit.jupiter.api.Test;

/**
 * Task policies on the way in and on the way out.
 *
 * <p>Four policies reach {@code tasks().create()}: escalation (the response clock), SLA (the
 * completion clock, shelf life and rejection budget), schedule (when a task may be offered at
 * all) and recurrence (a standing template). They are separate objects because they answer
 * separate questions and are stored separately.
 *
 * <p>The distinction these tests protect is <em>absent</em> versus <em>null</em>. Omitting a
 * policy inherits the workspace default; sending an explicit null opts the task out of it. Gson
 * omits nulls by default, which is right for every other field and wrong for exactly these two —
 * hence {@code escalationNone()} / {@code slaNone()}.
 */
class TaskPoliciesTest extends MockServerBase {

    @Test
    void every_policy_is_sent_as_its_own_wire_object() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing"))
                .escalation(new EscalationPolicy(60_000)
                        .onNoResponse("block")
                        .priorityBoost(10)
                        .tiers(List.of(List.of("billing"), List.of("billing", "english")))
                        .maxEscalations(2)
                        .onExhausted("park"))
                .sla(new SlaPolicy()
                        .completeWithinMs(3_600_000)
                        .expireAfterMs(86_400_000)
                        .maxRejections(3)
                        .onCompletionBreach("notify")
                        .onMaxRejections("park")
                        .onExpire("drop"))
                .schedule(new SchedulePolicy().notBefore(1_756_000_000_000L).notAfter(1_756_003_600_000L)
                        .onMiss("park")));

        JsonObject body = bodyOf(takeRequest());
        JsonObject escalation = body.getAsJsonObject("escalation");
        assertEquals(60_000, escalation.get("respondWithinMs").getAsLong());
        assertEquals("block", escalation.get("onNoResponse").getAsString());
        assertEquals(2, escalation.getAsJsonArray("tiers").size());
        assertEquals("park", escalation.get("onExhausted").getAsString());

        JsonObject sla = body.getAsJsonObject("sla");
        assertEquals(3_600_000, sla.get("completeWithinMs").getAsLong());
        assertEquals(3, sla.get("maxRejections").getAsInt());
        assertEquals("drop", sla.get("onExpire").getAsString());

        JsonObject schedule = body.getAsJsonObject("schedule");
        assertEquals(1_756_000_000_000L, schedule.get("notBefore").getAsLong());
        assertEquals("park", schedule.get("onMiss").getAsString());
    }

    @Test
    void unset_fields_inside_a_policy_are_omitted_rather_than_nulled() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing"))
                .escalation(new EscalationPolicy(30_000)));

        // A null inside a policy object reads as "clear this setting", not "I did not set it".
        JsonObject escalation = bodyOf(takeRequest()).getAsJsonObject("escalation");
        assertEquals(1, escalation.size());
        assertEquals(30_000, escalation.get("respondWithinMs").getAsLong());
    }

    @Test
    void omitting_a_policy_leaves_it_off_the_body_entirely() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing")));

        // Absent means "inherit the workspace default" — not the SDK's decision to make.
        JsonObject body = bodyOf(takeRequest());
        assertFalse(body.has("escalation"));
        assertFalse(body.has("sla"));
    }

    @Test
    void opting_out_of_a_workspace_default_sends_an_explicit_null() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing")).escalationNone().slaNone());

        // The one case where a null must survive Gson's omit-nulls rule.
        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.has("escalation"));
        assertTrue(body.get("escalation").isJsonNull());
        assertTrue(body.has("sla"));
        assertTrue(body.get("sla").isJsonNull());
    }

    @Test
    void opting_one_policy_out_leaves_the_other_free_to_carry_a_value() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing"))
                .escalationNone()
                .sla(new SlaPolicy().completeWithinMs(1000)));

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("escalation").isJsonNull());
        assertEquals(1000, body.getAsJsonObject("sla").get("completeWithinMs").getAsLong());
    }

    @Test
    void opting_only_the_sla_out_leaves_escalation_absent() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing")).slaNone());

        // The two opt-outs are independent: nulling the SLA must not drag escalation onto the
        // wire as a null too, which would silently opt the task out of a default it wanted.
        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("sla").isJsonNull());
        assertFalse(body.has("escalation"));
    }

    @Test
    void required_skills_and_the_two_team_fields_reach_the_body() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing"))
                .requiredSkills(List.of(new RequiredSkill("sk_node", 3)))
                .teamId("team_1"));

        JsonObject body = bodyOf(takeRequest());
        assertEquals("sk_node",
                body.getAsJsonArray("requiredSkills").get(0).getAsJsonObject().get("skillId").getAsString());
        assertEquals("team_1", body.get("teamId").getAsString());
    }

    @Test
    void a_soft_team_preference_is_never_sent_as_the_hard_gate() throws Exception {
        enqueueJson(202, "{\"id\":\"task_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("billing")).preferTeamId("team_1"));

        // teamId excludes everyone else; preferTeamId only reorders. Confusing them changes
        // who can take the task, not just who takes it first.
        JsonObject body = bodyOf(takeRequest());
        assertEquals("team_1", body.get("preferTeamId").getAsString());
        assertFalse(body.has("teamId"));
    }

    @Test
    void a_recurrence_carries_its_whole_clock() throws Exception {
        enqueueJson(202, "{\"id\":\"nightly\",\"status\":\"recurring\"}");

        client().tasks().create(new CreateTask(List.of("ops"))
                .id("nightly")
                .recurrence(new RecurrencePolicy(86_400_000)
                        .startAt(1_756_000_000_000L)
                        .windowMs(3_600_000)
                        .onMiss("park")
                        .until(1_788_000_000_000L)
                        .maxOccurrences(30)
                        .catchUp("all")));

        JsonObject recurrence = bodyOf(takeRequest()).getAsJsonObject("recurrence");
        assertEquals(86_400_000, recurrence.get("everyMs").getAsLong());
        assertEquals(1_756_000_000_000L, recurrence.get("startAt").getAsLong());
        assertEquals(30, recurrence.get("maxOccurrences").getAsInt());
        assertEquals("all", recurrence.get("catchUp").getAsString());
    }

    @Test
    void a_minimal_recurrence_sends_only_the_interval() throws Exception {
        enqueueJson(202, "{\"id\":\"nightly\",\"status\":\"recurring\"}");

        client().tasks().create(new CreateTask(List.of("ops")).recurrence(new RecurrencePolicy(60_000)));

        // catchUp defaults to "skip" server-side; echoing it would claim a choice nobody made.
        JsonObject recurrence = bodyOf(takeRequest()).getAsJsonObject("recurrence");
        assertEquals(1, recurrence.size());
        assertEquals(60_000, recurrence.get("everyMs").getAsLong());
    }

    @Test
    void a_task_reads_back_its_policies_and_its_ladder_position() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"tags\":[\"billing\"],\"priority\":90,\"status\":\"queued\","
                + "\"workerId\":null,\"createdAt\":1756000000000,\"skillThresholds\":{\"billing\":3},"
                + "\"escalation\":{\"respondWithinMs\":60000,\"onNoResponse\":\"block\"},"
                + "\"escalationLevel\":2,\"sla\":{\"completeWithinMs\":3600000},"
                + "\"schedule\":{\"notBefore\":1756000000000}}");

        Task task = client().tasks().get("task_1");

        assertEquals(60_000, task.getEscalation().getRespondWithinMs());
        assertEquals("block", task.getEscalation().getOnNoResponse());
        // Without escalationLevel a client can render the ladder but not where the task sits.
        assertEquals(2, task.getEscalationLevel());
        assertEquals(3_600_000, task.getSla().getCompleteWithinMs());
        assertEquals(1_756_000_000_000L, task.getSchedule().getNotBefore());
        // The gate a queued task is waiting on — the answer to "why is this still queued?"
        assertEquals(3.0, task.getSkillThresholds().get("billing"));
    }

    @Test
    void a_task_without_policies_reads_them_as_null_not_as_empty_objects() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"tags\":[],\"priority\":null,\"status\":\"queued\","
                + "\"workerId\":null,\"createdAt\":1,\"escalation\":null,\"sla\":null}");

        Task task = client().tasks().get("task_1");

        // An empty SlaPolicy would read as "an SLA with no deadlines", a different fact.
        assertNull(task.getEscalation());
        assertNull(task.getSla());
        assertNull(task.getEscalationLevel());
    }
}
