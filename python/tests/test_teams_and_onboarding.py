"""Teams, QR join links, and worker identities — the roster-building surface.

These three groups are how a workspace gets workers in the first place, and they share one
hazard: several of them return a URL that is credential-equivalent until it is consumed. The
tests below pin the wire shape *and* the fact that those URLs survive parsing intact, because a
dropped `invite_url` leaves an operator with no way to onboard on a deployment whose mailer is
not configured — the common case, not the edge case.
"""

from __future__ import annotations

import asyncio

from fivexer import (
    CLEAR,
    CreateJoinLink,
    CreateWorkerIdentity,
    InviteWorkerIdentity,
    PatchTeam,
    UpdateWorkerIdentity,
    UpsertTeam,
    WorkerSkillAssignment,
)
from tests.conftest import empty_response, json_response, read_body

TEAM = {
    "id": "team_1",
    "key": "billing",
    "tag": "team:billing",
    "name": "Billing",
    "description": "Invoices and dunning",
    "color": "#5b8def",
    "createdAt": "2026-08-01T09:00:00.000Z",
}

IDENTITY = {
    "id": "wid_1",
    "workerId": "agent_1",
    "label": "Ada",
    "email": "ada@example.com",
    "status": "active",
    "hasPin": False,
    "activatedAt": None,
    "createdAt": "2026-08-01T09:00:00.000Z",
    "revokedAt": None,
}


# ---- teams ---------------------------------------------------------------


def test_creating_a_team_sends_key_and_name_and_omits_unset_fields(server, client):
    server.set_response(json_response(201, TEAM))

    team = client.teams.create(UpsertTeam(key="billing", name="Billing"))

    assert server.last.method == "POST"
    assert server.last.url.path == "/v1/teams"
    assert read_body(server.last) == {"key": "billing", "name": "Billing"}
    assert team.tag == "team:billing"


def test_a_teams_tag_is_what_makes_it_a_routing_primitive(server, client):
    # A team is not just a label: matching sees `tag`, so parsing it is load-bearing.
    server.set_response(json_response(200, TEAM))

    assert client.teams.get("team_1").tag == "team:billing"


def test_patching_a_team_omits_absent_fields_so_stored_values_survive(server, client):
    server.set_response(json_response(200, TEAM))

    client.teams.patch("team_1", PatchTeam(name="Billing EU"))

    assert server.last.method == "PATCH"
    assert read_body(server.last) == {"name": "Billing EU"}


def test_listing_teams_unwraps_the_envelope(server, client):
    server.set_response(json_response(200, {"teams": [TEAM, {**TEAM, "id": "team_2"}]}))

    teams = client.teams.list()

    assert [t.id for t in teams] == ["team_1", "team_2"]


def test_team_members_carry_their_role_and_join_time(server, client):
    server.set_response(
        json_response(
            200,
            {
                "teamId": "team_1",
                "members": [
                    {"workerId": "agent_1", "role": "lead", "addedAt": "2026-08-02T10:00:00.000Z"},
                    {"workerId": "agent_2", "role": "member", "addedAt": "2026-08-03T10:00:00.000Z"},
                ],
            },
        )
    )

    result = client.teams.members("team_1")

    assert result.team_id == "team_1"
    assert [(m.worker_id, m.role) for m in result.members] == [("agent_1", "lead"), ("agent_2", "member")]


def test_setting_members_replaces_the_roster_wholesale(server, client):
    # PUT, not PATCH — a worker absent from the list is removed, and the verb is the only
    # warning a caller gets.
    server.set_response(json_response(200, {"teamId": "team_1", "workerIds": ["agent_1"]}))

    result = client.teams.set_members("team_1", ["agent_1"])

    assert server.last.method == "PUT"
    assert server.last.url.path == "/v1/teams/team_1/members"
    assert read_body(server.last) == {"workerIds": ["agent_1"]}
    assert result.worker_ids == ["agent_1"]


def test_removing_a_team_tolerates_an_empty_204(server, client):
    server.set_response(empty_response(204))

    assert client.teams.remove("team_1") is None
    assert server.last.method == "DELETE"


# ---- join links ----------------------------------------------------------


def test_creating_a_join_link_returns_the_url_only_once(server, client):
    link = {
        "id": "jl_1",
        "label": "Warehouse hires",
        "teamId": "team_1",
        "tags": ["warehouse"],
        "requiresApproval": True,
        "maxUses": 25,
        "useCount": 0,
        "status": "active",
        "createdAt": "2026-08-01T09:00:00.000Z",
        "expiresAt": "2026-09-01T09:00:00.000Z",
        "revokedAt": None,
    }
    server.set_response(json_response(201, {"link": link, "joinUrl": "https://5xer.com/join/abc"}))

    result = client.join_links.create(
        CreateJoinLink(
            label="Warehouse hires",
            team_id="team_1",
            tags=["warehouse"],
            skills=[WorkerSkillAssignment(skill_id="sk_1", level=3)],
            requires_approval=True,
            max_uses=25,
        )
    )

    body = read_body(server.last)
    assert body["label"] == "Warehouse hires"
    assert body["skills"] == [{"skillId": "sk_1", "level": 3}]
    assert body["requiresApproval"] is True
    # A lost join_url is re-created, never recovered — losing it in parsing is unrecoverable.
    assert result.join_url == "https://5xer.com/join/abc"
    assert result.link.max_uses == 25


def test_a_join_link_with_no_cap_parses_max_uses_as_none_not_zero(server, client):
    # None means unlimited; 0 would mean exhausted. Collapsing them inverts the meaning.
    server.set_response(
        json_response(
            200,
            {
                "links": [
                    {
                        "id": "jl_1",
                        "label": "Open",
                        "teamId": None,
                        "tags": [],
                        "requiresApproval": False,
                        "maxUses": None,
                        "useCount": 7,
                        "status": "active",
                        "createdAt": "2026-08-01T09:00:00.000Z",
                        "expiresAt": "2026-09-01T09:00:00.000Z",
                        "revokedAt": None,
                    }
                ]
            },
        )
    )

    links = client.join_links.list()

    assert links[0].max_uses is None
    assert links[0].use_count == 7


def test_revoking_a_join_link_is_a_delete(server, client):
    server.set_response(empty_response(204))

    client.join_links.revoke("jl_1")

    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/join-links/jl_1"


# ---- worker identities ---------------------------------------------------


def test_inviting_a_worker_reports_whether_the_mail_actually_went_out(server, client):
    # `mailer_unconfigured` is the common deployment state, and the only signal telling an
    # operator they must hand the link over themselves.
    server.set_response(
        json_response(
            201,
            {
                "identity": IDENTITY,
                "workerCreated": True,
                "emailStatus": "mailer_unconfigured",
                "inviteUrl": "https://5xer.com/v1/worker-auth/invite?token=t0k",
            },
        )
    )

    result = client.identities.invite(InviteWorkerIdentity(email="ada@example.com", label="Ada"))

    assert server.last.url.path == "/v1/worker-identities/invite"
    assert read_body(server.last) == {"email": "ada@example.com", "label": "Ada"}
    assert result.email_status == "mailer_unconfigured"
    assert result.invite_url.endswith("token=t0k")
    assert result.worker_created is True


def test_resending_an_invite_needs_no_body(server, client):
    server.set_response(
        json_response(
            200,
            {"identity": IDENTITY, "workerCreated": False, "emailStatus": "sent", "inviteUrl": "https://x/i"},
        )
    )

    result = client.identities.resend_invite("agent_1")

    assert server.last.url.path == "/v1/worker-identities/agent_1/invite/resend"
    assert result.worker_created is False


def test_listing_identities_never_exposes_a_pin_only_whether_one_is_set(server, client):
    server.set_response(json_response(200, {"identities": [IDENTITY]}))

    identities = client.identities.list()

    assert identities[0].has_pin is False
    assert not hasattr(identities[0], "pin")


def test_creating_an_identity_directly_unwraps_the_envelope(server, client):
    server.set_response(json_response(201, {"identity": {**IDENTITY, "hasPin": True}}))

    identity = client.identities.create("agent_1", CreateWorkerIdentity(label="Ada", pin="4821"))

    assert server.last.url.path == "/v1/workers/agent_1/identity"
    assert read_body(server.last) == {"label": "Ada", "pin": "4821"}
    # The caller gets the identity itself, not a one-field wrapper around it.
    assert identity.has_pin is True


def test_an_unset_email_is_absent_from_an_identity_patch(server, client):
    server.set_response(json_response(200, {"identity": IDENTITY}))

    client.identities.update("agent_1", UpdateWorkerIdentity(label="Ada L."))

    assert read_body(server.last) == {"label": "Ada L."}


def test_clearing_an_identity_email_sends_an_explicit_null(server, client):
    # Absent means "leave it"; null means "erase it". Without the sentinel there is no way to
    # say the second, and an operator cannot remove an address that has changed hands.
    server.set_response(json_response(200, {"identity": {**IDENTITY, "email": None}}))

    client.identities.update("agent_1", UpdateWorkerIdentity(email=CLEAR))

    assert read_body(server.last) == {"email": None}


def test_revoking_an_identity_is_a_status_patch_not_a_delete(server, client):
    server.set_response(json_response(200, {"identity": {**IDENTITY, "status": "revoked"}}))

    identity = client.identities.update("agent_1", UpdateWorkerIdentity(status="revoked"))

    assert read_body(server.last) == {"status": "revoked"}
    assert identity.status == "revoked"


def test_removing_an_identity_leaves_the_worker_itself_alone(server, client):
    # DELETE /workers/{id}/identity, not DELETE /workers/{id} — the worker stays routable,
    # they just lose the ability to sign in.
    server.set_response(empty_response(204))

    client.identities.remove("agent_1")

    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/workers/agent_1/identity"


# ---- async parity spot-checks --------------------------------------------
# The suite has no pytest-asyncio; async tests drive the loop themselves.


def test_async_teams_mirror_the_sync_wire(server, async_client):
    server.set_response(json_response(200, {"teams": [TEAM]}))

    async def main():
        try:
            return await async_client.teams.list()
        finally:
            await async_client.aclose()

    teams = asyncio.run(main())

    assert server.last.url.path == "/v1/teams"
    assert teams[0].tag == "team:billing"


def test_async_identity_invite_mirrors_the_sync_wire(server, async_client):
    server.set_response(
        json_response(
            201,
            {"identity": IDENTITY, "workerCreated": True, "emailStatus": "sent", "inviteUrl": "https://x/i"},
        )
    )

    async def main():
        try:
            return await async_client.identities.invite(InviteWorkerIdentity(email="ada@example.com"))
        finally:
            await async_client.aclose()

    result = asyncio.run(main())

    assert read_body(server.last) == {"email": "ada@example.com"}
    assert result.identity.worker_id == "agent_1"


def test_async_join_link_create_mirrors_the_sync_wire(server, async_client):
    server.set_response(
        json_response(
            201,
            {
                "link": {
                    "id": "jl_1",
                    "label": "Open",
                    "teamId": None,
                    "tags": [],
                    "requiresApproval": False,
                    "maxUses": None,
                    "useCount": 0,
                    "status": "active",
                    "createdAt": "2026-08-01T09:00:00.000Z",
                    "expiresAt": "2026-09-01T09:00:00.000Z",
                    "revokedAt": None,
                },
                "joinUrl": "https://5xer.com/join/abc",
            },
        )
    )

    async def main():
        try:
            return await async_client.join_links.create(CreateJoinLink(label="Open"))
        finally:
            await async_client.aclose()

    assert asyncio.run(main()).join_url == "https://5xer.com/join/abc"


def test_async_team_roster_and_removal_mirror_the_sync_wire(server, async_client):
    server.set_responder(
        lambda request: empty_response(204)
        if request.method == "DELETE"
        else json_response(200, {"teamId": "team_1", "workerIds": ["agent_1"]})
    )

    async def main():
        try:
            roster = await async_client.teams.set_members("team_1", ["agent_1"])
            await async_client.teams.remove("team_1")
            return roster
        finally:
            await async_client.aclose()

    assert asyncio.run(main()).worker_ids == ["agent_1"]


def test_async_identity_lifecycle_mirrors_the_sync_wire(server, async_client):
    def respond(request):
        if request.method == "DELETE":
            return empty_response(204)
        if request.url.path.endswith("/worker-identities"):
            return json_response(200, {"identities": [IDENTITY]})
        if request.url.path.endswith("/join-links"):
            return json_response(200, {"links": []})
        if "/teams" in request.url.path:
            return json_response(200, {"teamId": "team_1", "members": [], **TEAM})
        return json_response(
            200,
            {"identity": IDENTITY, "workerCreated": False, "emailStatus": "sent", "inviteUrl": "https://x/i"},
        )

    server.set_responder(respond)

    async def main():
        try:
            listed = await async_client.identities.list()
            await async_client.identities.create("agent_1", CreateWorkerIdentity(label="Ada", pin="4821"))
            await async_client.identities.update("agent_1", UpdateWorkerIdentity(status="revoked"))
            resent = await async_client.identities.resend_invite("agent_1")
            await async_client.identities.remove("agent_1")
            await async_client.join_links.revoke("jl_1")
            await async_client.join_links.list()
            await async_client.teams.create(UpsertTeam(key="k", name="K"))
            await async_client.teams.get("team_1")
            await async_client.teams.patch("team_1", PatchTeam(name="K2"))
            await async_client.teams.members("team_1")
            return listed, resent
        finally:
            await async_client.aclose()

    listed, _ = asyncio.run(main())
    assert listed[0].worker_id == "agent_1"
