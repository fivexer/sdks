"""The supervisor plane: a third credential type that watches and unblocks work.

The distinguishing property is that there is nothing to recover with. A worker session
refreshes; a supervisor session is redeemed from a single-use link and, once expired, can only
be replaced by a new link. So the tests pin that the client adopts a session on accept, drops
it on logout, and never invents a recovery path in between — and that scope refusals reach the
caller rather than being smoothed over.
"""

from __future__ import annotations

import asyncio

import pytest

from fivexer import (
    AcceptSupervisorInvite,
    FivexerApiError,
    PushSubscriptionInput,
    UnparkTask,
)
from tests.conftest import empty_response, json_response, read_body

SESSION = {
    "token": "sv_new",
    "expiresAt": 1_756_000_000_000,
    "supervisor": {"id": "sup_1", "label": "Dana", "email": "dana@example.com", "teamId": "team_1"},
    "workspaceId": "ws_1",
}


# ---- session ---------------------------------------------------------------


def test_redeeming_a_link_adopts_the_session(server, anon_supervisor):
    # Redeeming is the only way in, so a client that failed to adopt here would leave every
    # later call unauthenticated.
    server.set_response(json_response(201, SESSION))

    session = anon_supervisor.accept_invite(AcceptSupervisorInvite(token="link_tok"))

    assert server.last.url.path == "/v1/supervisor-auth/accept"
    assert read_body(server.last) == {"token": "link_tok"}
    assert session.supervisor.label == "Dana"
    assert anon_supervisor.session_token == "sv_new"


def test_expiry_is_epoch_ms_not_the_iso_string_the_worker_plane_sends(server, anon_supervisor):
    # The two planes genuinely differ on the wire. Normalising here would hide that from
    # anyone comparing the two clients side by side.
    server.set_response(json_response(201, SESSION))

    session = anon_supervisor.accept_invite(AcceptSupervisorInvite(token="link_tok"))

    assert session.expires_at == 1_756_000_000_000
    assert isinstance(session.expires_at, int)
    assert anon_supervisor.session_expires_at == 1_756_000_000_000


def test_a_spent_link_is_one_indistinct_400(server, anon_supervisor):
    # Expired, already-used, revoked and never-existed all answer identically — the server
    # refuses to tell a grinder which half of a guess was right.
    server.set_response(json_response(400, {"error": {"code": "invalid_token", "message": "ask for a new one"}}))

    with pytest.raises(FivexerApiError) as raised:
        anon_supervisor.accept_invite(AcceptSupervisorInvite(token="spent"))

    assert raised.value.code == "invalid_token"
    assert anon_supervisor.session_token is None


def test_the_entry_point_is_unauthenticated(server, anon_supervisor):
    server.set_response(json_response(200, {"consoleUrl": "https://console.5xer.com"}))

    entry = anon_supervisor.entry()

    assert server.last.url.path == "/v1/supervisor-auth/entry"
    assert server.last.headers.get("authorization") is None
    assert entry.console_url == "https://console.5xer.com"


def test_a_deployment_with_no_dashboard_origin_reports_a_null_console_url(server, anon_supervisor):
    server.set_response(json_response(200, {"consoleUrl": None}))

    assert anon_supervisor.entry().console_url is None


def test_authenticated_calls_carry_the_bearer_token(server, supervisor):
    server.set_response(json_response(200, {"supervisorId": "sup_1", "teamKey": "billing"}))

    supervisor.me()

    assert server.last.headers["authorization"] == "Bearer sv_s3ss10n"


def test_logging_out_forgets_the_token(server, supervisor):
    server.set_response(empty_response(204))

    supervisor.logout()

    assert server.last.url.path == "/v1/supervisor-auth/logout"
    assert supervisor.session_token is None
    assert supervisor.session_expires_at is None


def test_a_token_can_be_adopted_after_construction(server, anon_supervisor):
    server.set_response(json_response(200, {"consoleUrl": None}))

    anon_supervisor.set_token("sv_later", 1_756_000_000_000)
    anon_supervisor.entry()

    assert server.last.headers["authorization"] == "Bearer sv_later"
    assert anon_supervisor.session_expires_at == 1_756_000_000_000


def test_a_base_url_is_required():
    with pytest.raises(ValueError, match="base_url is required"):
        __import__("fivexer").FivexerSupervisor(base_url="")


# ---- the board -------------------------------------------------------------


def test_a_null_team_key_means_the_whole_workspace_not_a_missing_value(server, supervisor):
    server.set_response(
        json_response(
            200,
            {
                "supervisorId": "sup_1",
                "label": "Dana",
                "email": None,
                "workspaceId": "ws_1",
                "teamKey": None,
            },
        )
    )

    me = supervisor.me()

    assert me.team_key is None
    assert me.workspace_id == "ws_1"


def test_the_whole_board_arrives_in_one_request(server, supervisor):
    # Deliberately one endpoint rather than four: this is a phone on a depot floor, and four
    # round trips over a bad connection show a board that assembles itself in pieces.
    server.set_response(
        json_response(
            200,
            {
                "teamKey": "billing",
                "counts": {"queued": 4, "pending": 2, "parked": 1, "oldestWaitMs": 90_000},
                "crew": [
                    {"workerId": "agent_1", "backlog": 3, "available": True},
                    {"workerId": "agent_2", "backlog": 0, "available": False},
                ],
                "parked": [
                    {
                        "id": "task_8fk2",
                        "tags": ["billing"],
                        "priority": 90,
                        "createdAt": 1_754_000_000_000,
                    }
                ],
            },
        )
    )

    board = supervisor.overview()

    assert len(server.requests) == 1
    assert server.last.url.path == "/v1/supervisor/overview"
    assert board.counts.oldest_wait_ms == 90_000
    # Busiest first, so the board's top row is the one needing attention.
    assert board.crew[0].backlog == 3
    assert board.parked[0].id == "task_8fk2"


def test_an_empty_board_parses_without_inventing_defaults(server, supervisor):
    server.set_response(json_response(200, {"teamKey": None}))

    board = supervisor.overview()

    assert board.counts.queued == 0
    assert board.crew == []
    assert board.parked == []


def test_a_parked_task_with_no_priority_parses_as_none(server, supervisor):
    server.set_response(
        json_response(
            200,
            {"parked": [{"id": "t1", "tags": [], "priority": None, "createdAt": None}]},
        )
    )

    parked = supervisor.overview().parked[0]

    assert parked.priority is None
    assert parked.created_at is None


# ---- actions ---------------------------------------------------------------


def test_unparking_with_no_options_sends_an_empty_body(server, supervisor):
    server.set_response(json_response(200, {"id": "t1", "status": "queued"}))

    supervisor.unpark("t1")

    assert server.last.url.path == "/v1/supervisor/tasks/t1/unpark"
    assert read_body(server.last) == {}


def test_unpark_reset_flags_reach_the_wire(server, supervisor):
    server.set_response(json_response(200, {"id": "t1", "status": "queued"}))

    supervisor.unpark("t1", UnparkTask(reset_escalation=True, reset_sla=True))

    assert read_body(server.last) == {"resetEscalation": True, "resetSla": True}


def test_reprioritising_a_task(server, supervisor):
    server.set_response(json_response(200, {"id": "t1", "priority": 99}))

    result = supervisor.set_priority("t1", 99)

    assert server.last.url.path == "/v1/supervisor/tasks/t1/priority"
    assert read_body(server.last) == {"priority": 99}
    assert result.priority == 99


def test_handing_a_task_to_a_crew_member(server, supervisor):
    server.set_response(json_response(200, {"id": "t1", "workerId": "agent_1", "status": "pending"}))

    result = supervisor.assign("t1", "agent_1", force=True)

    assert server.last.url.path == "/v1/supervisor/tasks/t1/assign"
    assert read_body(server.last) == {"force": True, "workerId": "agent_1"}
    assert result.worker_id == "agent_1"


def test_an_unset_force_flag_is_absent_from_the_assign_body(server, supervisor):
    server.set_response(json_response(200, {"id": "t1", "workerId": "agent_1", "status": "pending"}))

    supervisor.assign("t1", "agent_1")

    assert read_body(server.last) == {"workerId": "agent_1"}


def test_a_refused_assignment_reaches_the_caller_as_assign_blocked(server, supervisor):
    # The matcher throws on a refusal (paused, backlog full, prior rejection); the API turns
    # that into a 400 an operator can read, and it must not be swallowed here.
    server.set_response(json_response(400, {"error": {"code": "assign_blocked", "message": "worker is paused"}}))

    with pytest.raises(FivexerApiError) as raised:
        supervisor.assign("t1", "agent_1")

    assert raised.value.code == "assign_blocked"
    assert raised.value.status_code == 400


def test_a_task_outside_this_crew_is_refused_not_silently_moved(server, supervisor):
    server.set_response(json_response(403, {"error": {"code": "out_of_scope", "message": "not your crew"}}))

    with pytest.raises(FivexerApiError) as raised:
        supervisor.unpark("someone_elses")

    assert raised.value.status_code == 403


def test_pausing_a_crew_member_holds_their_backlog_by_default(server, supervisor):
    # Pausing never releases implicitly — that is the engine's rule, and the empty list is how
    # the caller sees the backlog held.
    server.set_response(json_response(200, {"workerId": "agent_1", "available": False, "releasedTaskIds": []}))

    result = supervisor.set_availability("agent_1", False)

    assert server.last.url.path == "/v1/supervisor/workers/agent_1/availability"
    assert read_body(server.last) == {"available": False}
    assert result.released_task_ids == []


def test_releasing_the_backlog_names_what_moved(server, supervisor):
    server.set_response(
        json_response(200, {"workerId": "agent_1", "available": False, "releasedTaskIds": ["t1", "t2"]})
    )

    result = supervisor.set_availability("agent_1", False, release_backlog=True)

    assert read_body(server.last) == {"releaseBacklog": True, "available": False}
    assert result.released_task_ids == ["t1", "t2"]


def test_ids_cannot_escape_their_path_segment(server, supervisor):
    server.set_response(json_response(200, {"id": "x", "status": "queued"}))

    supervisor.unpark("a/../b")

    assert server.last.url.raw_path.decode().startswith("/v1/supervisor/tasks/a%2F..%2Fb/unpark")


# ---- push ------------------------------------------------------------------


def test_push_config_reports_a_deployment_without_vapid_keys_as_disabled(server, supervisor):
    server.set_response(json_response(200, {"enabled": False, "publicKey": None}))

    config = supervisor.push_config()

    assert server.last.url.path == "/v1/supervisor/push/config"
    assert config.enabled is False
    assert config.public_key is None


def test_subscribing_nests_the_browser_keys_and_returns_the_bare_ack(server, supervisor):
    # Unlike the worker plane this returns only an ack — the supervisor table is keyed by
    # endpoint and has nothing else to hand back.
    server.set_response(json_response(201, {"ok": True}))

    accepted = supervisor.push_subscribe(PushSubscriptionInput(endpoint="https://fcm/x", p256dh="p2", auth="au"))

    assert server.last.url.path == "/v1/supervisor/push/subscriptions"
    assert read_body(server.last) == {
        "endpoint": "https://fcm/x",
        "keys": {"p256dh": "p2", "auth": "au"},
    }
    assert accepted is True


def test_unsubscribing_sends_only_the_endpoint(server, supervisor):
    server.set_response(empty_response(204))

    supervisor.push_unsubscribe("https://fcm/x")

    assert server.last.method == "DELETE"
    assert read_body(server.last) == {"endpoint": "https://fcm/x"}


def test_an_expired_session_is_a_401_with_no_recovery_attempted(server, supervisor):
    # There is no refresh endpoint on this plane: a dead session means "get a new link".
    # Retrying here would only double the failed calls.
    server.set_response(json_response(401, {"error": {"code": "unauthorized", "message": "session expired"}}))

    with pytest.raises(FivexerApiError) as raised:
        supervisor.me()

    assert raised.value.status_code == 401
    assert len(server.requests) == 1


def test_a_read_is_retried_on_a_429_honouring_retry_after(server):
    import httpx as _httpx

    from fivexer import FivexerSupervisor
    from tests.conftest import BASE_URL

    attempts = {"n": 0}

    def responder(request):
        attempts["n"] += 1
        if attempts["n"] == 1:
            return _httpx.Response(
                429, json={"error": {"code": "rate_limited", "message": "slow"}}, headers={"retry-after": "0"}
            )
        return _httpx.Response(200, json={"consoleUrl": None})

    server.set_responder(responder)
    client = FivexerSupervisor(
        BASE_URL, max_retries=1, http_client=_httpx.Client(transport=_httpx.MockTransport(server.handler))
    )

    client.entry()

    assert attempts["n"] == 2


# ---- async parity ----------------------------------------------------------


def test_async_supervisor_mirrors_the_sync_wire(server, async_supervisor):
    def respond(request):
        path = request.url.path
        if request.method == "DELETE":
            return empty_response(204)
        if path.endswith("/supervisor-auth/entry"):
            return json_response(200, {"consoleUrl": "https://console.5xer.com"})
        if path.endswith("/supervisor-auth/accept"):
            return json_response(201, SESSION)
        if path.endswith("/supervisor-auth/logout"):
            return empty_response(204)
        if path.endswith("/supervisor/me"):
            return json_response(200, {"supervisorId": "sup_1", "teamKey": None})
        if path.endswith("/supervisor/overview"):
            return json_response(200, {"teamKey": None, "counts": {"queued": 1}})
        if path.endswith("/unpark"):
            return json_response(200, {"id": "t1", "status": "queued"})
        if path.endswith("/priority"):
            return json_response(200, {"id": "t1", "priority": 50})
        if path.endswith("/assign"):
            return json_response(200, {"id": "t1", "workerId": "agent_1", "status": "pending"})
        if path.endswith("/availability"):
            return json_response(200, {"workerId": "agent_1", "available": True, "releasedTaskIds": []})
        if path.endswith("/push/config"):
            return json_response(200, {"enabled": True, "publicKey": "BPk"})
        if path.endswith("/push/subscriptions"):
            return json_response(201, {"ok": True})
        return json_response(200, {})

    server.set_responder(respond)

    async def main():
        try:
            return (
                await async_supervisor.entry(),
                await async_supervisor.me(),
                await async_supervisor.overview(),
                await async_supervisor.unpark("t1", UnparkTask(reset_sla=True)),
                await async_supervisor.set_priority("t1", 50),
                await async_supervisor.assign("t1", "agent_1"),
                await async_supervisor.set_availability("agent_1", True),
                await async_supervisor.push_config(),
                await async_supervisor.push_subscribe(PushSubscriptionInput(endpoint="e", p256dh="p", auth="a")),
                await async_supervisor.push_unsubscribe("e"),
            )
        finally:
            await async_supervisor.aclose()

    entry, me, board, unpark, priority, assign, avail, cfg, sub, unsub = asyncio.run(main())

    assert entry.console_url == "https://console.5xer.com"
    assert me.team_key is None
    assert board.counts.queued == 1
    assert unpark.status == "queued"
    assert priority.priority == 50
    assert assign.worker_id == "agent_1"
    assert avail.available is True
    assert cfg.enabled is True
    assert sub is True
    assert unsub is None


def test_async_accept_and_logout_manage_the_session(server, async_supervisor):
    server.set_responder(
        lambda request: empty_response(204) if request.url.path.endswith("logout") else json_response(201, SESSION)
    )

    async def main():
        try:
            session = await async_supervisor.accept_invite(AcceptSupervisorInvite(token="t"))
            adopted = async_supervisor.session_token
            await async_supervisor.logout()
            return session, adopted, async_supervisor.session_token
        finally:
            await async_supervisor.aclose()

    session, adopted, after = asyncio.run(main())

    assert session.workspace_id == "ws_1"
    assert adopted == "sv_new"
    assert after is None


def test_async_context_manager_and_token_helpers(server):
    import httpx as _httpx

    from fivexer import AsyncFivexerSupervisor
    from tests.conftest import BASE_URL

    server.set_response(json_response(200, {"consoleUrl": None}))

    async def main():
        async with AsyncFivexerSupervisor(
            BASE_URL,
            max_retries=0,
            http_client=_httpx.AsyncClient(transport=_httpx.MockTransport(server.handler)),
        ) as client:
            client.set_token("sv_x", 1)
            await client.entry()
            return client.base_url, client.session_token, client.session_expires_at

    base, token, expires = asyncio.run(main())

    assert base == BASE_URL
    assert token == "sv_x"
    assert expires == 1


def test_async_read_is_retried_on_a_429(server):
    import httpx as _httpx

    from fivexer import AsyncFivexerSupervisor
    from tests.conftest import BASE_URL

    attempts = {"n": 0}

    def responder(request):
        attempts["n"] += 1
        if attempts["n"] == 1:
            return _httpx.Response(
                429, json={"error": {"code": "rate_limited", "message": "slow"}}, headers={"retry-after": "0"}
            )
        return _httpx.Response(200, json={"consoleUrl": None})

    server.set_responder(responder)

    async def main():
        client = AsyncFivexerSupervisor(
            BASE_URL,
            max_retries=1,
            http_client=_httpx.AsyncClient(transport=_httpx.MockTransport(server.handler)),
        )
        try:
            await client.entry()
        finally:
            await client.aclose()

    asyncio.run(main())

    assert attempts["n"] == 2


def test_an_async_base_url_is_required():
    from fivexer import AsyncFivexerSupervisor

    with pytest.raises(ValueError, match="base_url is required"):
        AsyncFivexerSupervisor(base_url="")


def test_the_sync_client_closes_the_transport_it_owns():
    from fivexer import FivexerSupervisor

    with FivexerSupervisor("https://api.fivexer.test", token="sv_x") as client:
        assert client.base_url == "https://api.fivexer.test"
        assert client.session_token == "sv_x"
        assert client.session_expires_at is None
    # Closing is idempotent — a second call must not raise on an already-closed transport.
    client.close()


def test_a_caller_supplied_transport_is_left_open(server):
    import httpx as _httpx

    from fivexer import FivexerSupervisor
    from tests.conftest import BASE_URL

    transport = _httpx.Client(transport=_httpx.MockTransport(server.handler))
    client = FivexerSupervisor(BASE_URL, http_client=transport)

    client.close()

    # The client did not create it, so it does not get to close it — the caller may still be
    # using the same pool for other planes.
    assert transport.is_closed is False
    transport.close()


def test_a_trailing_slash_on_the_base_url_is_stripped(server):
    import httpx as _httpx

    from fivexer import FivexerSupervisor

    server.set_response(json_response(200, {"consoleUrl": None}))
    client = FivexerSupervisor(
        "https://api.fivexer.test/",
        max_retries=0,
        http_client=_httpx.Client(transport=_httpx.MockTransport(server.handler)),
    )

    client.entry()

    # Not "//v1/..." — a doubled slash is a 404 on most gateways.
    assert str(server.last.url) == "https://api.fivexer.test/v1/supervisor-auth/entry"
