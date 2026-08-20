package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.CreateJoinLink;
import io.fivexer.sdk.model.CreateJoinLinkResult;
import io.fivexer.sdk.model.CreateWorkerIdentity;
import io.fivexer.sdk.model.InviteWorkerIdentity;
import io.fivexer.sdk.model.JoinLink;
import io.fivexer.sdk.model.PatchTeam;
import io.fivexer.sdk.model.PublicWorkerIdentity;
import io.fivexer.sdk.model.Team;
import io.fivexer.sdk.model.TeamMembers;
import io.fivexer.sdk.model.TeamRoster;
import io.fivexer.sdk.model.UpdateWorkerIdentity;
import io.fivexer.sdk.model.UpsertTeam;
import io.fivexer.sdk.model.WorkerInviteResult;
import io.fivexer.sdk.model.WorkerSkillAssignment;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Teams, QR join links and worker identities — the roster-building surface.
 *
 * <p>These share one hazard the rest of the API does not: several return a URL that is
 * credential-equivalent until it is consumed. The tests pin the wire shape and the fact that
 * those URLs survive parsing, because a dropped {@code inviteUrl} leaves an operator with no way
 * to onboard on a deployment whose mailer is unconfigured — the common case, not the edge case.
 */
class TeamsAndOnboardingTest extends MockServerBase {

    private static final String TEAM = "{\"id\":\"team_1\",\"key\":\"billing\",\"tag\":\"team:billing\","
            + "\"name\":\"Billing\",\"description\":\"Invoices\",\"color\":\"#5b8def\","
            + "\"createdAt\":\"2026-08-01T09:00:00.000Z\"}";

    private static final String IDENTITY = "{\"id\":\"wid_1\",\"workerId\":\"agent_1\",\"label\":\"Ada\","
            + "\"email\":\"ada@example.com\",\"status\":\"active\",\"hasPin\":false,"
            + "\"activatedAt\":null,\"createdAt\":\"2026-08-01T09:00:00.000Z\",\"revokedAt\":null}";

    // ---- teams ----

    @Test
    void creating_a_team_sends_key_and_name_and_omits_unset_fields() throws Exception {
        enqueueJson(201, TEAM);

        Team team = client().teams().create(new UpsertTeam("billing", "Billing"));

        RecordedRequest request = takeRequest();
        assertEquals("POST", request.getMethod());
        assertEquals("/v1/teams", pathOf(request));
        JsonObject body = bodyOf(request);
        assertEquals("billing", body.get("key").getAsString());
        assertFalse(body.has("description"));
        // The derived tag is what matching sees, so parsing it is load-bearing.
        assertEquals("team:billing", team.getTag());
    }

    @Test
    void patching_a_team_omits_absent_fields_so_stored_values_survive() throws Exception {
        enqueueJson(200, TEAM);

        client().teams().patch("team_1", new PatchTeam().name("Billing EU"));

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        JsonObject body = bodyOf(request);
        assertEquals("Billing EU", body.get("name").getAsString());
        assertEquals(1, body.size());
    }

    @Test
    void listing_teams_unwraps_the_envelope() throws Exception {
        enqueueJson(200, "{\"teams\":[" + TEAM + "]}");

        List<Team> teams = client().teams().list();

        assertEquals(1, teams.size());
        assertEquals("team_1", teams.get(0).getId());
    }

    @Test
    void an_absent_teams_array_reads_as_empty_rather_than_null() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().teams().list().isEmpty());
    }

    @Test
    void team_members_carry_their_role_and_join_time() throws Exception {
        enqueueJson(200, "{\"teamId\":\"team_1\",\"members\":["
                + "{\"workerId\":\"agent_1\",\"role\":\"lead\",\"addedAt\":\"2026-08-02T10:00:00Z\"}]}");

        TeamMembers members = client().teams().members("team_1");

        assertEquals("team_1", members.getTeamId());
        assertEquals("lead", members.getMembers().get(0).getRole());
    }

    @Test
    void setting_members_replaces_the_roster_wholesale() throws Exception {
        // PUT, not PATCH — a worker absent from the list is removed, and the verb is the only
        // warning a caller gets.
        enqueueJson(200, "{\"teamId\":\"team_1\",\"workerIds\":[\"agent_1\"]}");

        TeamRoster roster = client().teams().setMembers("team_1", List.of("agent_1"));

        RecordedRequest request = takeRequest();
        assertEquals("PUT", request.getMethod());
        assertEquals("/v1/teams/team_1/members", pathOf(request));
        assertEquals("agent_1", bodyOf(request).getAsJsonArray("workerIds").get(0).getAsString());
        assertEquals(List.of("agent_1"), roster.getWorkerIds());
    }

    @Test
    void removing_a_team_tolerates_an_empty_204() throws Exception {
        enqueueEmpty(204);

        client().teams().remove("team_1");

        assertEquals("DELETE", takeRequest().getMethod());
    }

    @Test
    void getting_a_team_by_id_encodes_the_segment() throws Exception {
        enqueueJson(200, TEAM);

        client().teams().get("team/../admin");

        assertEquals("/v1/teams/team%2F..%2Fadmin", pathOf(takeRequest()));
    }

    // ---- join links ----

    @Test
    void creating_a_join_link_returns_the_url_only_once() throws Exception {
        enqueueJson(201, "{\"link\":{\"id\":\"jl_1\",\"label\":\"Warehouse\",\"teamId\":\"team_1\","
                + "\"tags\":[\"warehouse\"],\"requiresApproval\":true,\"maxUses\":25,\"useCount\":0,"
                + "\"status\":\"active\",\"createdAt\":\"x\",\"expiresAt\":\"y\",\"revokedAt\":null},"
                + "\"joinUrl\":\"https://5xer.com/join/abc\"}");

        CreateJoinLinkResult result = client().joinLinks().create(
                new CreateJoinLink("Warehouse")
                        .teamId("team_1")
                        .tags(List.of("warehouse"))
                        .skills(List.of(new WorkerSkillAssignment("sk_1", 3)))
                        .requiresApproval(true)
                        .maxUses(25));

        RecordedRequest request = takeRequest();
        JsonObject body = bodyOf(request);
        assertEquals("Warehouse", body.get("label").getAsString());
        assertEquals("sk_1", body.getAsJsonArray("skills").get(0).getAsJsonObject()
                .get("skillId").getAsString());
        // A lost joinUrl is re-created, never recovered — losing it in parsing is unrecoverable.
        assertEquals("https://5xer.com/join/abc", result.getJoinUrl());
        assertEquals(25, result.getLink().getMaxUses());
    }

    @Test
    void a_join_link_with_no_cap_parses_max_uses_as_null_not_zero() throws Exception {
        // null means unlimited; 0 would mean exhausted. Collapsing them inverts the meaning.
        enqueueJson(200, "{\"links\":[{\"id\":\"jl_1\",\"label\":\"Open\",\"teamId\":null,"
                + "\"tags\":[],\"requiresApproval\":false,\"maxUses\":null,\"useCount\":7,"
                + "\"status\":\"active\",\"createdAt\":\"x\",\"expiresAt\":\"y\",\"revokedAt\":null}]}");

        List<JoinLink> links = client().joinLinks().list();

        assertNull(links.get(0).getMaxUses());
        assertEquals(7, links.get(0).getUseCount());
    }

    @Test
    void revoking_a_join_link_is_a_delete() throws Exception {
        enqueueEmpty(204);

        client().joinLinks().revoke("jl_1");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/join-links/jl_1", pathOf(request));
    }

    @Test
    void an_absent_links_array_reads_as_empty() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().joinLinks().list().isEmpty());
    }

    // ---- worker identities ----

    @Test
    void inviting_a_worker_reports_whether_the_mail_actually_went_out() throws Exception {
        // mailer_unconfigured is the common deployment state, and the only signal telling an
        // operator they must hand the link over themselves.
        enqueueJson(201, "{\"identity\":" + IDENTITY + ",\"workerCreated\":true,"
                + "\"emailStatus\":\"mailer_unconfigured\",\"inviteUrl\":\"https://5xer.com/i?token=t0k\"}");

        WorkerInviteResult result = client().identities().invite(
                new InviteWorkerIdentity("ada@example.com").label("Ada"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/worker-identities/invite", pathOf(request));
        assertEquals("ada@example.com", bodyOf(request).get("email").getAsString());
        assertEquals("mailer_unconfigured", result.getEmailStatus());
        assertTrue(result.getInviteUrl().endsWith("token=t0k"));
        assertTrue(result.getWorkerCreated());
    }

    @Test
    void resending_an_invite_needs_no_body() throws Exception {
        enqueueJson(200, "{\"identity\":" + IDENTITY + ",\"workerCreated\":false,"
                + "\"emailStatus\":\"sent\",\"inviteUrl\":\"https://x/i\"}");

        WorkerInviteResult result = client().identities().resendInvite("agent_1");

        assertEquals("/v1/worker-identities/agent_1/invite/resend", pathOf(takeRequest()));
        assertFalse(result.getWorkerCreated());
    }

    @Test
    void listing_identities_never_exposes_a_pin_only_whether_one_is_set() throws Exception {
        enqueueJson(200, "{\"identities\":[" + IDENTITY + "]}");

        List<PublicWorkerIdentity> identities = client().identities().list();

        assertFalse(identities.get(0).getHasPin());
    }

    @Test
    void an_absent_identities_array_reads_as_empty() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().identities().list().isEmpty());
    }

    @Test
    void creating_an_identity_directly_unwraps_the_envelope() throws Exception {
        enqueueJson(201, "{\"identity\":{\"id\":\"wid_1\",\"workerId\":\"agent_1\",\"label\":\"Ada\","
                + "\"status\":\"active\",\"hasPin\":true}}");

        PublicWorkerIdentity identity = client().identities().create(
                "agent_1", new CreateWorkerIdentity("Ada", "4821"));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/workers/agent_1/identity", pathOf(request));
        assertEquals("4821", bodyOf(request).get("pin").getAsString());
        // The caller gets the identity itself, not a one-field wrapper around it.
        assertTrue(identity.getHasPin());
    }

    @Test
    void an_unset_email_is_absent_from_an_identity_patch() throws Exception {
        enqueueJson(200, "{\"identity\":" + IDENTITY + "}");

        client().identities().update("agent_1", new UpdateWorkerIdentity().label("Ada L."));

        JsonObject body = bodyOf(takeRequest());
        assertEquals("Ada L.", body.get("label").getAsString());
        assertFalse(body.has("email"));
    }

    @Test
    void clearing_an_identity_email_sends_an_explicit_null() throws Exception {
        // Absent means "leave it"; null means "erase it". Gson drops nulls, so this is the one
        // input model that cannot go through the shared serialiser.
        enqueueJson(200, "{\"identity\":" + IDENTITY + "}");

        client().identities().update("agent_1", new UpdateWorkerIdentity().clearEmail());

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.has("email"));
        assertTrue(body.get("email").isJsonNull());
    }

    @Test
    void setting_an_email_after_clearing_it_wins() throws Exception {
        // The flag must not outlive the decision that set it, or a later email() would silently
        // still erase the address.
        enqueueJson(200, "{\"identity\":" + IDENTITY + "}");

        client().identities().update("agent_1",
                new UpdateWorkerIdentity().clearEmail().email("new@example.com"));

        JsonObject body = bodyOf(takeRequest());
        assertEquals("new@example.com", body.get("email").getAsString());
    }

    @Test
    void revoking_an_identity_is_a_status_patch_not_a_delete() throws Exception {
        enqueueJson(200, "{\"identity\":" + IDENTITY + "}");

        client().identities().update("agent_1", new UpdateWorkerIdentity().status("revoked").pin("9137"));

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        JsonObject body = bodyOf(request);
        assertEquals("revoked", body.get("status").getAsString());
        assertEquals("9137", body.get("pin").getAsString());
    }

    @Test
    void removing_an_identity_leaves_the_worker_itself_alone() throws Exception {
        // DELETE /workers/{id}/identity, not DELETE /workers/{id} — the worker stays routable,
        // they just lose the ability to sign in.
        enqueueEmpty(204);

        client().identities().remove("agent_1");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/workers/agent_1/identity", pathOf(request));
    }
}
