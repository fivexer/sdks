package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.AcceptSupervisorInvite;
import io.fivexer.sdk.model.PushSubscriptionInput;
import io.fivexer.sdk.model.SupervisorAvailabilityResult;
import io.fivexer.sdk.model.SupervisorMe;
import io.fivexer.sdk.model.SupervisorOverview;
import io.fivexer.sdk.model.SupervisorSession;
import io.fivexer.sdk.model.UnparkTask;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * The supervisor plane: a third credential type that watches and unblocks work.
 *
 * <p>The distinguishing property is that there is nothing to recover with. A worker session
 * refreshes; a supervisor session is redeemed from a single-use link and, once expired, can only
 * be replaced by a new link. These tests pin that the client adopts a session on accept, drops
 * it on logout, and never invents a recovery path in between.
 */
class SupervisorTest extends MockServerBase {

    private static final String SESSION = "{\"token\":\"sv_new\",\"expiresAt\":1756000000000,"
            + "\"supervisor\":{\"id\":\"sup_1\",\"label\":\"Dana\",\"email\":\"dana@example.com\","
            + "\"teamId\":\"team_1\"},\"workspaceId\":\"ws_1\"}";

    private FivexerSupervisor supervisor() {
        return new FivexerSupervisor(server.url("/").toString(), "sv_s3ss10n", null, 0);
    }

    private FivexerSupervisor anonymous() {
        return new FivexerSupervisor(server.url("/").toString(), null, null, 0);
    }

    // ---- session ----

    @Test
    void redeeming_a_link_adopts_the_session() throws Exception {
        // Redeeming is the only way in, so a client that failed to adopt here would leave every
        // later call unauthenticated.
        enqueueJson(201, SESSION);

        FivexerSupervisor client = anonymous();
        SupervisorSession session = client.acceptInvite(new AcceptSupervisorInvite("link_tok"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor-auth/accept", pathOf(request));
        assertEquals("link_tok", bodyOf(request).get("token").getAsString());
        assertEquals("Dana", session.getSupervisor().getLabel());
        assertEquals("sv_new", client.getSessionToken());
    }

    @Test
    void expiry_is_epoch_millis_not_the_iso_string_the_worker_plane_sends() throws Exception {
        // The two planes genuinely differ on the wire. Normalising here would hide that from
        // anyone comparing the two clients side by side.
        enqueueJson(201, SESSION);

        FivexerSupervisor client = anonymous();
        SupervisorSession session = client.acceptInvite(new AcceptSupervisorInvite("link_tok"));

        assertEquals(1_756_000_000_000L, session.getExpiresAt());
        assertEquals(1_756_000_000_000L, client.getSessionExpiresAt());
    }

    @Test
    void a_spent_link_is_one_indistinct_400() throws Exception {
        // Expired, already-used, revoked and never-existed answer identically — the server
        // refuses to tell a grinder which half of a guess was right.
        enqueueJson(400, "{\"error\":{\"code\":\"invalid_token\",\"message\":\"ask for a new one\"}}");

        FivexerSupervisor client = anonymous();
        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client.acceptInvite(new AcceptSupervisorInvite("spent")));

        assertEquals("invalid_token", error.getCode());
        assertNull(client.getSessionToken());
    }

    @Test
    void the_entry_point_is_unauthenticated() throws Exception {
        enqueueJson(200, "{\"consoleUrl\":\"https://console.5xer.com\"}");

        assertEquals("https://console.5xer.com", anonymous().entry().getConsoleUrl());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor-auth/entry", pathOf(request));
        assertNull(request.getHeader("Authorization"));
    }

    @Test
    void a_deployment_with_no_dashboard_origin_reports_a_null_console_url() throws Exception {
        enqueueJson(200, "{\"consoleUrl\":null}");

        assertNull(anonymous().entry().getConsoleUrl());
    }

    @Test
    void authenticated_calls_carry_the_bearer_token() throws Exception {
        enqueueJson(200, "{\"supervisorId\":\"sup_1\",\"teamKey\":\"billing\"}");

        supervisor().me();

        assertEquals("Bearer sv_s3ss10n", takeRequest().getHeader("Authorization"));
    }

    @Test
    void logging_out_forgets_the_token() throws Exception {
        enqueueEmpty(204);

        FivexerSupervisor client = supervisor();
        client.logout();

        assertEquals("/v1/supervisor-auth/logout", pathOf(takeRequest()));
        assertNull(client.getSessionToken());
        assertNull(client.getSessionExpiresAt());
    }

    @Test
    void a_token_can_be_adopted_after_construction() throws Exception {
        enqueueJson(200, "{\"consoleUrl\":null}");

        FivexerSupervisor client = anonymous();
        client.setToken("sv_later", 1_756_000_000_000L);
        client.entry();

        assertEquals("Bearer sv_later", takeRequest().getHeader("Authorization"));
        assertEquals(1_756_000_000_000L, client.getSessionExpiresAt());
    }

    @Test
    void a_base_url_is_required() {
        assertThrows(IllegalArgumentException.class, () -> new FivexerSupervisor(""));
        assertThrows(IllegalArgumentException.class, () -> new FivexerSupervisor(null));
    }

    @Test
    void a_trailing_slash_on_the_base_url_is_stripped() throws Exception {
        // Not "//v1/..." — a doubled slash is a 404 on most gateways.
        enqueueJson(200, "{\"consoleUrl\":null}");

        new FivexerSupervisor(server.url("/").toString(), null, null, 0).entry();

        assertEquals("/v1/supervisor-auth/entry", pathOf(takeRequest()));
    }

    // ---- board ----

    @Test
    void a_null_team_key_means_the_whole_workspace_not_a_missing_value() throws Exception {
        enqueueJson(200, "{\"supervisorId\":\"sup_1\",\"label\":\"Dana\",\"email\":null,"
                + "\"workspaceId\":\"ws_1\",\"teamKey\":null}");

        SupervisorMe me = supervisor().me();

        assertNull(me.getTeamKey());
        assertEquals("ws_1", me.getWorkspaceId());
    }

    @Test
    void the_whole_board_arrives_in_one_request() throws Exception {
        // Deliberately one endpoint rather than four: this is a phone on a depot floor.
        enqueueJson(200, "{\"teamKey\":\"billing\",\"counts\":{\"queued\":4,\"pending\":2,"
                + "\"parked\":1,\"oldestWaitMs\":90000},\"crew\":["
                + "{\"workerId\":\"agent_1\",\"backlog\":3,\"available\":true},"
                + "{\"workerId\":\"agent_2\",\"backlog\":0,\"available\":false}],"
                + "\"parked\":[{\"id\":\"task_8fk2\",\"tags\":[\"billing\"],\"priority\":90,"
                + "\"createdAt\":1754000000000}]}");

        SupervisorOverview board = supervisor().overview();

        assertEquals(1, server.getRequestCount());
        assertEquals("/v1/supervisor/overview", pathOf(takeRequest()));
        assertEquals(90_000L, board.getCounts().getOldestWaitMs());
        // Busiest first, so the board's top row is the one needing attention.
        assertEquals(3, board.getCrew().get(0).getBacklog());
        assertEquals("task_8fk2", board.getParked().get(0).getId());
    }

    @Test
    void a_parked_task_with_no_priority_parses_as_null() throws Exception {
        enqueueJson(200, "{\"parked\":[{\"id\":\"t1\",\"tags\":[],\"priority\":null,\"createdAt\":null}]}");

        SupervisorOverview board = supervisor().overview();

        assertNull(board.getParked().get(0).getPriority());
        assertNull(board.getParked().get(0).getCreatedAt());
    }

    // ---- actions ----

    @Test
    void unparking_with_no_options_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"status\":\"queued\"}");

        supervisor().unpark("t1");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor/tasks/t1/unpark", pathOf(request));
        assertEquals(0, bodyOf(request).size());
    }

    @Test
    void unpark_reset_flags_reach_the_wire() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"status\":\"queued\"}");

        supervisor().unpark("t1", new UnparkTask().resetEscalation(true).resetSla(true));

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("resetEscalation").getAsBoolean());
        assertTrue(body.get("resetSla").getAsBoolean());
        assertFalse(body.has("resetSchedule"));
    }

    @Test
    void reprioritising_a_task() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"priority\":99}");

        assertEquals(99.0, supervisor().setPriority("t1", 99).getPriority());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor/tasks/t1/priority", pathOf(request));
        assertEquals(99.0, bodyOf(request).get("priority").getAsDouble());
    }

    @Test
    void handing_a_task_to_a_crew_member() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"workerId\":\"agent_1\",\"status\":\"pending\"}");

        assertEquals("agent_1", supervisor().assign("t1", "agent_1", true).getWorkerId());

        JsonObject body = bodyOf(takeRequest());
        assertEquals("agent_1", body.get("workerId").getAsString());
        assertTrue(body.get("force").getAsBoolean());
    }

    @Test
    void an_unset_force_flag_is_absent_from_the_assign_body() throws Exception {
        enqueueJson(200, "{\"id\":\"t1\",\"workerId\":\"agent_1\",\"status\":\"pending\"}");

        supervisor().assign("t1", "agent_1");

        JsonObject body = bodyOf(takeRequest());
        assertEquals(1, body.size());
        assertFalse(body.has("force"));
    }

    @Test
    void a_refused_assignment_reaches_the_caller_as_assign_blocked() throws Exception {
        // The matcher throws on a refusal; the API turns that into a 400 an operator can read,
        // and it must not be swallowed here.
        enqueueJson(400, "{\"error\":{\"code\":\"assign_blocked\",\"message\":\"worker is paused\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> supervisor().assign("t1", "agent_1"));

        assertEquals("assign_blocked", error.getCode());
        assertEquals(400, error.getStatusCode());
    }

    @Test
    void a_task_outside_this_crew_is_refused_not_silently_moved() throws Exception {
        enqueueJson(403, "{\"error\":{\"code\":\"out_of_scope\",\"message\":\"not your crew\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> supervisor().unpark("someone_elses"));

        assertEquals(403, error.getStatusCode());
    }

    @Test
    void pausing_a_crew_member_holds_their_backlog_by_default() throws Exception {
        // Pausing never releases implicitly — that is the engine's rule, and the empty list is
        // how the caller sees the backlog held.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"available\":false,\"releasedTaskIds\":[]}");

        SupervisorAvailabilityResult result = supervisor().setAvailability("agent_1", false);

        RecordedRequest request = takeRequest();
        assertEquals("/v1/supervisor/workers/agent_1/availability", pathOf(request));
        JsonObject body = bodyOf(request);
        assertFalse(body.get("available").getAsBoolean());
        assertFalse(body.has("releaseBacklog"));
        assertTrue(result.getReleasedTaskIds().isEmpty());
    }

    @Test
    void releasing_the_backlog_names_what_moved() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"available\":false,"
                + "\"releasedTaskIds\":[\"t1\",\"t2\"]}");

        SupervisorAvailabilityResult result = supervisor().setAvailability("agent_1", false, true);

        assertTrue(bodyOf(takeRequest()).get("releaseBacklog").getAsBoolean());
        assertEquals(2, result.getReleasedTaskIds().size());
    }

    @Test
    void ids_cannot_escape_their_path_segment() throws Exception {
        enqueueJson(200, "{\"id\":\"x\",\"status\":\"queued\"}");

        supervisor().unpark("a/../b");

        assertEquals("/v1/supervisor/tasks/a%2F..%2Fb/unpark", pathOf(takeRequest()));
    }

    // ---- push ----

    @Test
    void push_config_reports_a_deployment_without_vapid_keys_as_disabled() throws Exception {
        enqueueJson(200, "{\"enabled\":false,\"publicKey\":null}");

        assertFalse(supervisor().pushConfig().getEnabled());
        assertEquals("/v1/supervisor/push/config", pathOf(takeRequest()));
    }

    @Test
    void subscribing_nests_the_browser_keys_and_returns_the_bare_ack() throws Exception {
        // Unlike the worker plane this returns only an ack — the supervisor table is keyed by
        // endpoint and has nothing else to hand back.
        enqueueJson(201, "{\"ok\":true}");

        assertTrue(supervisor().pushSubscribe(new PushSubscriptionInput("https://fcm/x", "p2", "au")));

        JsonObject body = bodyOf(takeRequest());
        assertEquals("https://fcm/x", body.get("endpoint").getAsString());
        assertEquals("p2", body.getAsJsonObject("keys").get("p256dh").getAsString());
    }

    @Test
    void unsubscribing_sends_only_the_endpoint() throws Exception {
        enqueueEmpty(204);

        supervisor().pushUnsubscribe("https://fcm/x");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        JsonObject body = bodyOf(request);
        assertEquals("https://fcm/x", body.get("endpoint").getAsString());
        assertEquals(1, body.size());
    }

    // ---- errors ----

    @Test
    void an_expired_session_is_a_401_with_no_recovery_attempted() throws Exception {
        // There is no refresh endpoint on this plane: a dead session means "get a new link".
        enqueueJson(401, "{\"error\":{\"code\":\"unauthorized\",\"message\":\"expired\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> supervisor().me());

        assertEquals(401, error.getStatusCode());
        assertEquals(1, server.getRequestCount());
    }

    @Test
    void a_non_json_error_body_still_names_the_status() throws Exception {
        // A proxy's HTML error page must not turn into a parse failure that hides the 502.
        enqueueJson(502, "<html>bad gateway</html>");

        FivexerApiException error = assertThrows(FivexerApiException.class, () -> supervisor().overview());

        assertEquals(502, error.getStatusCode());
        assertEquals("unknown_error", error.getCode());
    }

    @Test
    void a_read_is_retried_once_on_a_429() throws Exception {
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow\"}}", "retry-after", "0");
        enqueueJson(200, "{\"consoleUrl\":null}");

        new FivexerSupervisor(server.url("/").toString(), null, null, 1).entry();

        assertEquals(2, server.getRequestCount());
    }

    @Test
    void an_action_is_never_retried_because_it_carries_no_idempotency_key() throws Exception {
        // A replayed assign could move a task twice, so only reads are retried.
        enqueueJson(429, "{\"error\":{\"code\":\"rate_limited\",\"message\":\"slow\"}}", "retry-after", "0");

        FivexerSupervisor client = new FivexerSupervisor(server.url("/").toString(), "sv_x", null, 1);

        assertThrows(FivexerApiException.class, () -> client.assign("t1", "agent_1"));
        assertEquals(1, server.getRequestCount());
    }
}
