package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.AcceptWorkerInvite;
import io.fivexer.sdk.model.AcceptWorkerInviteResult;
import io.fivexer.sdk.model.ChangePin;
import io.fivexer.sdk.model.JoinWorkspace;
import io.fivexer.sdk.model.JoinWorkspaceResult;
import io.fivexer.sdk.model.PushConfig;
import io.fivexer.sdk.model.PushSubscriptionInput;
import io.fivexer.sdk.model.WorkerDeviceInput;
import io.fivexer.sdk.model.WorkerLocation;
import io.fivexer.sdk.model.WorkerMe;
import io.fivexer.sdk.model.WorkerMetricsWindow;
import io.fivexer.sdk.model.WorkerSkillLevel;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * The worker's own view: signing in without a password, going on shift, and push.
 *
 * <p>This plane has one hazard the workspace plane does not: three calls ({@code join},
 * {@code acceptInvite}, {@code refresh}) <em>mint</em> a session, so each must adopt the returned
 * token or the very next call goes out unauthenticated. Those adoptions are asserted individually.
 */
class WorkerSelfServiceTest extends MockServerBase {

    // ---- sessions that mint a token ----

    @Test
    void joining_by_qr_adopts_the_returned_session() throws Exception {
        // The worker id is server-generated here, so adopting it is what makes every later call
        // (which defaults its worker id) work at all.
        enqueueJson(201, "{\"token\":\"wt_new\",\"expiresAt\":\"2026-09-01T00:00:00Z\","
                + "\"workspaceId\":\"ws_1\",\"workerId\":\"agent_9\",\"label\":\"Ada\","
                + "\"pendingApproval\":true,\"portalUrl\":\"https://5xer.com/portal/ws_1/\"}");

        FivexerWorker worker = anonymousWorker();
        JoinWorkspaceResult result = worker.join(new JoinWorkspace("jt", "Ada", "4821"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/worker-auth/join", pathOf(request));
        JsonObject body = bodyOf(request);
        assertEquals("jt", body.get("token").getAsString());
        assertFalse(body.has("email"));
        assertTrue(result.getPendingApproval());
        assertEquals("wt_new", worker.getSessionToken());
        assertEquals("agent_9", worker.getWorkerId());
    }

    @Test
    void a_join_carrying_an_email_includes_it() throws Exception {
        enqueueJson(201, "{\"token\":\"wt_new\",\"workspaceId\":\"ws_1\",\"workerId\":\"agent_9\"}");

        anonymousWorker().join(new JoinWorkspace("jt", "Ada", "4821").email("ada@example.com"));

        assertEquals("ada@example.com", bodyOf(takeRequest()).get("email").getAsString());
    }

    @Test
    void accepting_an_emailed_invite_signs_the_worker_in() throws Exception {
        // Setting a PIN *is* the sign-in — otherwise the worker's next act after choosing one
        // would be to retype it into a login form, which is what the magic link exists to avoid.
        enqueueJson(200, "{\"token\":\"wt_invited\",\"workerId\":\"agent_1\","
                + "\"workspaceId\":\"ws_1\",\"label\":\"Ada\",\"portalUrl\":\"https://x/\"}");

        FivexerWorker worker = anonymousWorker();
        AcceptWorkerInviteResult result = worker.acceptInvite(new AcceptWorkerInvite("it", "4821"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/worker-auth/accept-invite", pathOf(request));
        assertEquals("4821", bodyOf(request).get("pin").getAsString());
        assertEquals("agent_1", result.getWorkerId());
        assertEquals("wt_invited", worker.getSessionToken());
    }

    @Test
    void refresh_rotates_the_token_in_place() throws Exception {
        enqueueJson(200, "{\"token\":\"wt_rotated\",\"expiresAt\":\"2026-09-01T00:00:00Z\"}");

        FivexerWorker worker = worker();

        assertTrue(worker.refresh());
        assertEquals("/v1/worker-auth/refresh", pathOf(takeRequest()));
        assertEquals("wt_rotated", worker.getSessionToken());
    }

    @Test
    void refresh_without_a_token_short_circuits_without_a_request() {
        assertFalse(anonymousWorker().refresh());
        assertEquals(0, server.getRequestCount());
    }

    @Test
    void a_4xx_on_refresh_means_reauthenticate_not_crash() throws Exception {
        // The caller's job here is to show a login prompt, not to handle an exception.
        enqueueJson(401, "{\"error\":{\"code\":\"unauthorized\",\"message\":\"expired\"}}");

        FivexerWorker worker = worker();

        assertFalse(worker.refresh());
        // The dead token is left in place; only a successful rotation replaces it.
        assertEquals("wt_s3ss10n", worker.getSessionToken());
    }

    @Test
    void a_5xx_on_refresh_is_raised_so_the_current_token_stays_usable() throws Exception {
        // A transient server fault must not be mistaken for "your session ended".
        enqueueJson(503, "{\"error\":{\"code\":\"unavailable\",\"message\":\"down\"}}");

        FivexerWorker worker = worker();

        FivexerApiException error = assertThrows(FivexerApiException.class, worker::refresh);
        assertEquals(503, error.getStatusCode());
        assertEquals("wt_s3ss10n", worker.getSessionToken());
    }

    // ---- shift and identity ----

    @Test
    void me_reports_shift_state_and_whether_skills_still_need_setting() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"label\":\"Ada\",\"available\":false,"
                + "\"pendingApproval\":false,\"onBreak\":false,\"breakStartedAt\":null,"
                + "\"skills\":[{\"skillId\":\"sk_1\",\"key\":\"welsh\",\"name\":\"Welsh\",\"level\":3}],"
                + "\"skillSetupPending\":true}");

        WorkerMe me = worker().me();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/me", pathOf(request));
        assertEquals("Bearer wt_s3ss10n", request.getHeader("Authorization"));
        assertTrue(me.getSkillSetupPending());
        assertEquals("welsh", me.getSkills().get(0).getKey());
    }

    @Test
    void going_on_shift_is_the_workers_own_switch() throws Exception {
        // Workers are created off shift, so this call is what makes someone matchable at all.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"available\":true}");

        assertTrue(worker().setAvailability(true).getAvailable());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/me/availability", pathOf(request));
        JsonObject body = bodyOf(request);
        assertTrue(body.get("available").getAsBoolean());
        // No liveness contract unless one is asked for: an interactive client must not send it.
        assertFalse(body.has("staleAfterMs"));
    }

    @Test
    void an_unattended_worker_can_declare_how_long_its_silence_may_last() throws Exception {
        // The opt-in that lets the platform clock out a crashed daemon instead of leaving it
        // "available" forever. Only a headless client should ever send it.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"available\":true}");

        worker().setAvailability(true, 900000L);

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("available").getAsBoolean());
        assertEquals(900000L, body.get("staleAfterMs").getAsLong());
    }

    @Test
    void a_liveness_budget_is_never_sent_when_going_off_shift() throws Exception {
        // It only means anything alongside `available: true` — a paused worker is not silent,
        // it is off shift, and there is nothing left to clock out.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"available\":false}");

        worker().setAvailability(false, 900000L);

        JsonObject body = bodyOf(takeRequest());
        assertFalse(body.get("available").getAsBoolean());
        assertFalse(body.has("staleAfterMs"));
    }

    @Test
    void changing_a_pin_returns_nothing_and_keeps_the_session() throws Exception {
        enqueueEmpty(204);

        FivexerWorker worker = worker();
        worker.changePin(new ChangePin("1111", "4821"));

        JsonObject body = bodyOf(takeRequest());
        assertEquals("1111", body.get("currentPin").getAsString());
        assertEquals("4821", body.get("newPin").getAsString());
        assertEquals("wt_s3ss10n", worker.getSessionToken());
    }

    @Test
    void the_skill_catalog_is_read_only_and_unwrapped() throws Exception {
        // Inventing a skill is an operator's decision; a worker picks from this list or none.
        enqueueJson(200, "{\"skills\":[{\"id\":\"sk_1\",\"key\":\"welsh\",\"name\":\"Welsh\"}]}");

        assertEquals("welsh", worker().skillCatalog().get(0).getKey());
        assertEquals("/v1/portal/skills", pathOf(takeRequest()));
    }

    @Test
    void an_absent_catalog_array_reads_as_empty() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(worker().skillCatalog().isEmpty());
    }

    @Test
    void setting_skills_replaces_the_whole_set() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"skills\":[{\"skillId\":\"sk_1\","
                + "\"key\":\"welsh\",\"name\":\"Welsh\",\"level\":4}]}");

        worker().setSkills(List.of(new WorkerSkillLevel("sk_1", 4)));

        RecordedRequest request = takeRequest();
        assertEquals("PUT", request.getMethod());
        assertEquals("/v1/portal/me/skills", pathOf(request));
        JsonObject skill = bodyOf(request).getAsJsonArray("skills").get(0).getAsJsonObject();
        assertEquals("sk_1", skill.get("skillId").getAsString());
        assertEquals(4, skill.get("level").getAsInt());
    }

    // ---- comments, metrics, location ----

    @Test
    void a_worker_reads_comments_on_their_own_task() throws Exception {
        enqueueJson(200, "{\"comments\":[{\"id\":\"c1\",\"body\":\"called back\","
                + "\"createdAt\":1754000000000}],\"hasMore\":false}");

        assertEquals("called back", worker().comments("t1").getComments().get(0).getBody());
        assertEquals("/v1/portal/tasks/t1/comments", pathOf(takeRequest()));
    }

    @Test
    void worker_comment_paging_params_reach_the_wire() throws Exception {
        // The worker transport had no query support at all until this surface needed it; a
        // silently dropped cursor would page forever on the first page.
        enqueueJson(200, "{\"comments\":[],\"hasMore\":false}");

        worker().comments("t1", "c_42", 5);

        String query = queryOf(takeRequest());
        assertTrue(query.contains("cursor=c_42"), query);
        assertTrue(query.contains("limit=5"), query);
    }

    @Test
    void a_worker_adds_a_comment_with_a_bare_body_field() throws Exception {
        enqueueJson(201, "{\"comment\":{\"id\":\"c2\",\"body\":\"on my way\",\"createdAt\":1}}");

        // The envelope is unwrapped: the caller asked for a comment, not a wrapper.
        assertEquals("on my way", worker().addComment("t1", "on my way").getBody());
        assertEquals("on my way", bodyOf(takeRequest()).get("body").getAsString());
    }

    @Test
    void the_metrics_window_defaults_to_seven_days() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"2026-08-13\","
                + "\"days\":[{\"day\":\"2026-08-13\",\"completed\":4}],"
                + "\"medianWaitMs\":1200,\"medianCycleMs\":45000}");

        WorkerMetricsWindow metrics = worker().metricsWindow();

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/metrics", pathOf(request));
        assertEquals("window=7d", queryOf(request));
        assertEquals(4, metrics.getDays().get(0).getCompleted());
        assertEquals(45000L, metrics.getMedianCycleMs());
    }

    @Test
    void an_explicit_window_overrides_the_default_and_no_history_reads_as_null() throws Exception {
        // No data yet is null, not zero — a fresh worker has no median, not a median of nothing.
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"since\":\"x\",\"days\":[],"
                + "\"medianWaitMs\":null,\"medianCycleMs\":null}");

        WorkerMetricsWindow metrics = worker().metricsWindow("30d");

        assertEquals("window=30d", queryOf(takeRequest()));
        assertNull(metrics.getMedianWaitMs());
    }

    @Test
    void updating_location_sends_plain_coordinates() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"latitude\":59.4,\"longitude\":24.7}");

        assertEquals(24.7, worker().updateLocation(new WorkerLocation(59.4, 24.7)).getLongitude());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/workers/me/location", pathOf(request));
        assertEquals(59.4, bodyOf(request).get("latitude").getAsDouble());
    }

    // ---- push: native devices and web push ----

    @Test
    void registering_an_expo_device_carries_the_platform() throws Exception {
        enqueueJson(201, "{\"token\":\"ExpoTok\",\"platform\":\"ios\"}");

        assertEquals("ios", worker().registerDevice(new WorkerDeviceInput("ExpoTok").platform("ios"))
                .getPlatform());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/devices", pathOf(request));
        assertEquals("ExpoTok", bodyOf(request).get("token").getAsString());
    }

    @Test
    void unregistering_a_device_sends_the_token_in_the_body_of_a_delete() throws Exception {
        enqueueEmpty(204);

        worker().unregisterDevice("ExpoTok");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("ExpoTok", bodyOf(request).get("token").getAsString());
    }

    @Test
    void push_config_reports_a_deployment_without_vapid_keys_as_disabled() throws Exception {
        // Not an error: the portal still works, it just cannot push. Prompting for notification
        // permission here would burn the one prompt a browser gives you.
        enqueueJson(200, "{\"enabled\":false,\"publicKey\":null}");

        PushConfig config = worker().pushConfig();

        assertEquals("/v1/portal/push/config", pathOf(takeRequest()));
        assertFalse(config.getEnabled());
        assertNull(config.getPublicKey());
    }

    @Test
    void subscribing_nests_the_browser_keys_the_way_the_api_expects() throws Exception {
        // The browser hands over a flat-ish PushSubscription; the wire wants keys:{p256dh,auth}.
        enqueueJson(201, "{\"endpoint\":\"https://fcm/x\",\"createdAt\":\"2026-08-20T00:00:00Z\"}");

        assertEquals("https://fcm/x",
                worker().pushSubscribe(new PushSubscriptionInput("https://fcm/x", "p2", "au"))
                        .getEndpoint());

        RecordedRequest request = takeRequest();
        assertEquals("/v1/portal/push/subscriptions", pathOf(request));
        JsonObject body = bodyOf(request);
        assertEquals("https://fcm/x", body.get("endpoint").getAsString());
        assertEquals("p2", body.getAsJsonObject("keys").get("p256dh").getAsString());
        assertEquals("au", body.getAsJsonObject("keys").get("auth").getAsString());
    }

    @Test
    void unsubscribing_sends_only_the_endpoint() throws Exception {
        // By the time a browser fires `pushsubscriptionchange` it has already discarded the keys,
        // so requiring them here would make unsubscribe impossible in the case it exists for.
        enqueueEmpty(204);

        worker().pushUnsubscribe("https://fcm/x");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        JsonObject body = bodyOf(request);
        assertEquals("https://fcm/x", body.get("endpoint").getAsString());
        assertEquals(1, body.size());
    }
}
