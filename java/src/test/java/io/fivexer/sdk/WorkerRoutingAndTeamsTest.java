package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.LearnedWeightsPreview;
import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerDetail;
import io.fivexer.sdk.model.WorkerSessionToken;
import io.fivexer.sdk.model.WorkerLogin;
import io.fivexer.sdk.model.WorkspaceStats;
import java.util.List;
import java.util.Map;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Worker records: learned weights, team membership, and the two "not simply off shift" states.
 *
 * <p>The routing-weight fields exist as three separate maps because they answer three separate
 * questions: what is in force, which entries the learning layer owns, and what to restore on a
 * revert. Collapsing them would make "an operator vetoed this tag" indistinguishable from "the
 * model did" — the difference between a decision to keep and one to undo.
 */
class WorkerRoutingAndTeamsTest extends MockServerBase {

    private static final String DETAIL = "{\"id\":\"agent_1\",\"tags\":[\"english\",\"billing\"],"
            + "\"routingWeights\":{\"english\":1.0,\"billing\":0.0},"
            + "\"learnedRoutingWeights\":{\"billing\":0.0},"
            + "\"routingWeightsSnapshot\":{\"english\":1.0,\"billing\":1.0},"
            + "\"learnedRoutingWeightsSyncedAt\":1756000000000,\"skills\":null,"
            + "\"teams\":[{\"teamId\":\"team_1\",\"key\":\"support\",\"name\":\"Support\","
            + "\"color\":\"#4488ff\",\"role\":\"lead\"},"
            + "{\"teamId\":\"team_2\",\"key\":\"ops\",\"name\":\"Ops\",\"color\":null,\"role\":\"member\"}],"
            + "\"maxBacklogSize\":5,\"available\":false,\"invitePending\":true,"
            + "\"pendingApproval\":false,\"queueDepth\":2}";

    @Test
    void worker_detail_separates_learned_weights_from_the_ones_in_force() throws Exception {
        enqueueJson(200, DETAIL);

        WorkerDetail worker = client().workers().get("agent_1");

        assertEquals(0.0, worker.getRoutingWeights().get("billing"));
        // The veto on `billing` came from the model, not an operator — that is what makes it
        // revertible, and it is only knowable because the two maps stay separate.
        assertEquals(Map.of("billing", 0.0), worker.getLearnedRoutingWeights());
        assertEquals(1.0, worker.getRoutingWeightsSnapshot().get("billing"));
        assertEquals(1_756_000_000_000L, worker.getLearnedRoutingWeightsSyncedAt());
    }

    @Test
    void worker_detail_reads_team_membership_with_roles() throws Exception {
        enqueueJson(200, DETAIL);

        WorkerDetail worker = client().workers().get("agent_1");

        assertEquals(2, worker.getTeams().size());
        assertEquals("team_1", worker.getTeams().get(0).getTeamId());
        assertEquals("lead", worker.getTeams().get(0).getRole());
        assertEquals("Support", worker.getTeams().get(0).getName());
        assertNull(worker.getTeams().get(1).getColor());
    }

    @Test
    void unavailable_is_distinguished_from_invited_and_awaiting_approval() throws Exception {
        enqueueJson(200, DETAIL);

        WorkerDetail worker = client().workers().get("agent_1");

        // An operator UI that renders all three as "paused" tells the wrong story about all
        // three: nobody has to resume an invite, they have to chase it.
        assertFalse(worker.isAvailable());
        assertTrue(worker.isInvitePending());
        assertFalse(worker.isPendingApproval());
    }

    @Test
    void a_worker_with_no_teams_reads_as_null_rather_than_an_empty_list() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\",\"tags\":[],\"available\":true,\"queueDepth\":0}");

        WorkerDetail worker = client().workers().get("agent_1");

        // Null means "the server said nothing"; an empty list would claim membership of none.
        assertNull(worker.getTeams());
        assertNull(worker.getLearnedRoutingWeights());
        assertFalse(worker.isInvitePending());
    }

    @Test
    void queue_stats_carry_the_same_two_states_per_worker() throws Exception {
        enqueueJson(200, "{\"plan\":\"growth\",\"tasks\":{\"queued\":3},\"workers\":1,"
                + "\"queue\":{\"oldestWaitingMs\":4200,\"perWorker\":[{\"workerId\":\"agent_1\","
                + "\"backlog\":2,\"maxBacklogSize\":5,\"available\":false,\"invitePending\":false,"
                + "\"pendingApproval\":true}]}}");

        WorkspaceStats stats = client().stats();

        assertFalse(stats.getQueue().getPerWorker().get(0).isAvailable());
        assertTrue(stats.getQueue().getPerWorker().get(0).isPendingApproval());
        assertFalse(stats.getQueue().getPerWorker().get(0).isInvitePending());
    }

    @Test
    void upsert_can_set_team_membership_and_open_shift_state() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1")
                .tags(List.of("english"))
                .teamIds(List.of("team_1", "team_2"))
                .available(true));

        JsonObject body = bodyOf(takeRequest());
        assertEquals(2, body.getAsJsonArray("teamIds").size());
        // The escape hatch for programmatic fleets with no human at a portal: without it a newly
        // created agent worker is off shift and never matched.
        assertTrue(body.get("available").getAsBoolean());
    }

    @Test
    void an_empty_team_list_clears_membership_and_is_not_pruned() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").teamIds(List.of()));

        // An empty list is a wholesale replacement with nothing — the only way to remove a
        // worker from every team. Pruning it would silently turn a clear into a no-op.
        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.has("teamIds"));
        assertEquals(0, body.getAsJsonArray("teamIds").size());
    }

    @Test
    void omitting_team_ids_and_availability_leaves_both_untouched() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").tags(List.of("english")));

        JsonObject body = bodyOf(takeRequest());
        assertFalse(body.has("teamIds"));
        // Sending available=false here would clock a working person off shift on an unrelated
        // update, which is the whole reason this field is a Boolean rather than a boolean.
        assertFalse(body.has("available"));
    }

    @Test
    void a_worker_can_be_created_explicitly_off_shift() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").available(false));

        assertFalse(bodyOf(takeRequest()).get("available").getAsBoolean());
    }

    // ---- the session's own expiry ----

    @Test
    void logging_in_reports_when_the_session_expires() throws Exception {
        enqueueJson(200, "{\"token\":\"wt_new\",\"expiresAt\":\"2026-09-01T00:00:00.000Z\"}");

        WorkerSessionToken session = anonymousWorker().login(new WorkerLogin("ws_1", "agent_1", "4821"));

        // Rotating ahead of expiry needs the expiry. Without it a caller can only rotate on a
        // 401, which puts a failed request in front of a person at the start of every session.
        assertEquals("2026-09-01T00:00:00.000Z", session.getExpiresAt());
    }

    @Test
    void a_server_predating_the_refresh_endpoint_reports_no_expiry() throws Exception {
        enqueueJson(200, "{\"token\":\"wt_new\"}");

        WorkerSessionToken session = anonymousWorker().login(new WorkerLogin("ws_1", "agent_1", "4821"));

        // Null, not an invented timestamp: a made-up expiry rotates far too early, or never.
        assertNull(session.getExpiresAt());
    }

    // ---- learned-weight options ----

    @Test
    void previewing_weights_carries_the_two_widening_flags() throws Exception {
        enqueueJson(200, "{\"workers\":[]}");

        client().learning().previewWeights("agent_1", true, true);

        String query = queryOf(takeRequest());
        assertTrue(query.contains("workerId=agent_1"));
        assertTrue(query.contains("overrideManual=true"));
        assertTrue(query.contains("includeUnexploredTags=true"));
    }

    @Test
    void previewing_omits_flags_that_were_not_asked_for() throws Exception {
        enqueueJson(200, "{\"workers\":[]}");

        client().learning().previewWeights("agent_1", null, null);

        // Both default off server-side. Echoing false would make "I did not ask" look like a
        // deliberate refusal, and the preview would stop matching what a bare apply() writes.
        assertEquals("workerId=agent_1", queryOf(takeRequest()));
    }

    @Test
    void previewing_can_send_a_flag_off_explicitly() throws Exception {
        enqueueJson(200, "{\"workers\":[]}");

        LearnedWeightsPreview preview = client().learning().previewWeights(null, false, null);

        // An explicit false is a real instruction — "synthesize, but do not touch what an
        // operator set by hand" — and must reach the wire rather than being pruned as a default.
        assertEquals("overrideManual=false", queryOf(takeRequest()));
        assertTrue(preview.getWorkers().isEmpty());
    }

    @Test
    void applying_weights_sends_the_flags_in_the_body() throws Exception {
        enqueueJson(200, "{\"applied\":{\"agent_1\":{\"billing\":0.0}}}");

        Map<String, Map<String, Double>> applied =
                client().learning().applyWeights(List.of("agent_1"), true, false);

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("overrideManual").getAsBoolean());
        assertFalse(body.get("includeUnexploredTags").getAsBoolean());
        assertEquals(0.0, applied.get("agent_1").get("billing"));
    }

    @Test
    void applying_without_flags_sends_only_the_worker_ids() throws Exception {
        enqueueJson(200, "{\"applied\":{}}");

        client().learning().applyWeights(List.of("agent_1"));

        JsonObject body = bodyOf(takeRequest());
        assertEquals(1, body.size());
        assertTrue(body.has("workerIds"));
    }
}
