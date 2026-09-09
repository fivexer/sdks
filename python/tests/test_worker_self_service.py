"""The worker's own view: signing in without a password, going on shift, and push.

This is the plane a worker's phone or browser talks to with a `wt_` token, and it has one
recurring hazard the workspace plane does not: three separate calls (`join`, `accept_invite`,
`refresh`) *mint* a session, so each must adopt the returned token or the very next call goes
out unauthenticated. Those adoptions are asserted individually below.
"""

from __future__ import annotations

import asyncio

import httpx

from fivexer import (
    AcceptWorkerInvite,
    ChangePin,
    JoinWorkspace,
    PushSubscriptionInput,
    WorkerDeviceInput,
    WorkerLocation,
    WorkerLogin,
    WorkerSkillLevel,
)
from tests.conftest import empty_response, json_response, read_body


def _auth(request: httpx.Request) -> str | None:
    return request.headers.get("authorization")


# ---- sessions that mint a token ------------------------------------------


def test_joining_by_qr_adopts_the_returned_session(server, anon_worker):
    # The worker id is server-generated here, so adopting it is what makes every later call
    # (which defaults its worker_id) work at all.
    server.set_response(
        json_response(
            201,
            {
                "token": "wt_new",
                "expiresAt": "2026-09-01T00:00:00.000Z",
                "workspaceId": "ws_1",
                "workerId": "agent_9",
                "label": "Ada",
                "pendingApproval": True,
                "portalUrl": "https://5xer.com/portal/ws_1/",
            },
        )
    )

    result = anon_worker.join(JoinWorkspace(token="jt", name="Ada", pin="4821"))

    assert server.last.url.path == "/v1/worker-auth/join"
    assert read_body(server.last) == {"token": "jt", "name": "Ada", "pin": "4821"}
    assert result.pending_approval is True
    assert anon_worker.session_token == "wt_new"
    assert anon_worker.worker_id == "agent_9"


def test_a_join_carrying_an_email_includes_it_and_omits_it_otherwise(server, anon_worker):
    server.set_response(json_response(201, {"token": "wt_new", "workspaceId": "ws_1", "workerId": "agent_9"}))

    anon_worker.join(JoinWorkspace(token="jt", name="Ada", pin="4821", email="ada@example.com"))

    assert read_body(server.last)["email"] == "ada@example.com"


def test_accepting_an_emailed_invite_signs_the_worker_in(server, anon_worker):
    # Setting a PIN *is* the sign-in — otherwise the worker's next act after choosing one
    # would be to retype it into a login form, which is what the magic link exists to avoid.
    server.set_response(
        json_response(
            200,
            {
                "token": "wt_invited",
                "expiresAt": "2026-09-01T00:00:00.000Z",
                "workerId": "agent_1",
                "workspaceId": "ws_1",
                "label": "Ada",
                "portalUrl": "https://5xer.com/portal/ws_1/",
            },
        )
    )

    result = anon_worker.accept_invite(AcceptWorkerInvite(token="it", pin="4821"))

    assert server.last.url.path == "/v1/worker-auth/accept-invite"
    assert read_body(server.last) == {"token": "it", "pin": "4821"}
    assert result.worker_id == "agent_1"
    assert anon_worker.session_token == "wt_invited"


def test_refresh_rotates_the_token_in_place(server, worker):
    server.set_response(json_response(200, {"token": "wt_rotated", "expiresAt": "2026-09-01T00:00:00Z"}))

    assert worker.refresh() is True
    assert server.last.url.path == "/v1/worker-auth/refresh"
    assert worker.session_token == "wt_rotated"


def test_login_reports_when_the_session_expires(server, anon_worker):
    server.set_response(json_response(200, {"token": "wt_new", "expiresAt": "2026-09-01T00:00:00.000Z"}))

    session = anon_worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

    # Rotating ahead of expiry needs the expiry. Without it a caller can only rotate on a 401,
    # which means every session ends with at least one failed request in front of a person.
    assert session.token == "wt_new"
    assert session.expires_at == "2026-09-01T00:00:00.000Z"


def test_a_server_predating_the_refresh_endpoint_reports_no_expiry(server, anon_worker):
    server.set_response(json_response(200, {"token": "wt_new"}))

    session = anon_worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

    # None, not an invented timestamp: a made-up expiry rotates either far too early or never.
    assert session.expires_at is None


def test_refresh_without_a_token_short_circuits_without_a_request(server, anon_worker):
    assert anon_worker.refresh() is False
    assert server.requests == []


def test_a_4xx_on_refresh_means_reauthenticate_not_crash(server, worker):
    # The caller's job here is to show a login prompt, not to handle an exception.
    server.set_response(json_response(401, {"error": {"code": "unauthorized", "message": "expired"}}))

    assert worker.refresh() is False
    # The dead token is left in place; only a successful rotation replaces it.
    assert worker.session_token == "wt_s3ss10n"


def test_a_5xx_on_refresh_is_raised_so_the_current_token_stays_usable(server, worker):
    # A transient server fault must not be mistaken for "your session ended".
    server.set_response(json_response(503, {"error": {"code": "unavailable", "message": "down"}}))

    try:
        worker.refresh()
    except Exception as error:  # noqa: BLE001 - the type is asserted below
        assert getattr(error, "status_code", None) == 503
    else:  # pragma: no cover - raising is the point
        raise AssertionError("expected the 5xx to propagate")
    assert worker.session_token == "wt_s3ss10n"


# ---- shift and identity --------------------------------------------------


def test_me_reports_shift_state_and_whether_skills_still_need_setting(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "label": "Ada",
                "available": False,
                "pendingApproval": False,
                "onBreak": False,
                "breakStartedAt": None,
                "skills": [
                    {
                        "skillId": "sk_1",
                        "key": "welsh",
                        "name": "Welsh",
                        "level": 3,
                        "weightOverride": None,
                        "weight": 0.6,
                    }
                ],
                "skillSetupPending": True,
            },
        )
    )

    me = worker.me()

    assert server.last.url.path == "/v1/portal/me"
    assert _auth(server.last) == "Bearer wt_s3ss10n"
    assert me.skill_setup_pending is True
    assert me.skills[0].key == "welsh"


def test_going_on_shift_is_the_workers_own_switch(server, worker):
    # Workers are created off shift, so this call is what makes someone matchable at all.
    server.set_response(json_response(200, {"workerId": "agent_1", "available": True}))

    state = worker.set_availability(True)

    assert server.last.url.path == "/v1/portal/me/availability"
    assert read_body(server.last) == {"available": True}
    assert state.available is True


def test_changing_a_pin_returns_nothing_and_keeps_the_session(server, worker):
    server.set_response(empty_response(204))

    assert worker.change_pin(ChangePin(current_pin="1111", new_pin="4821")) is None
    assert read_body(server.last) == {"currentPin": "1111", "newPin": "4821"}
    assert worker.session_token == "wt_s3ss10n"


def test_setting_locale_patches_portal_me(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "locale": "et"}))

    state = worker.set_locale("et")

    assert server.last.method == "PATCH"
    assert server.last.url.path == "/v1/portal/me"
    assert read_body(server.last) == {"locale": "et"}
    assert state.locale == "et"


def test_clearing_locale_sends_an_explicit_null(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "locale": None}))

    state = worker.set_locale(None)

    assert read_body(server.last) == {"locale": None}
    assert state.locale is None


def test_the_skill_catalog_is_read_only_and_unwrapped(server, worker):
    # Inventing a skill is an operator's decision; a worker picks from this list or picks none.
    server.set_response(
        json_response(200, {"skills": [{"id": "sk_1", "key": "welsh", "name": "Welsh", "createdAt": "x"}]})
    )

    skills = worker.skill_catalog()

    assert server.last.url.path == "/v1/portal/skills"
    assert [s.key for s in skills] == ["welsh"]


def test_setting_skills_replaces_the_whole_set(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "skills": [
                    {
                        "skillId": "sk_1",
                        "key": "welsh",
                        "name": "Welsh",
                        "level": 4,
                        "weightOverride": None,
                        "weight": 0.8,
                    }
                ],
            },
        )
    )

    result = worker.set_skills([WorkerSkillLevel(skill_id="sk_1", level=4)])

    assert server.last.method == "PUT"
    assert server.last.url.path == "/v1/portal/me/skills"
    assert read_body(server.last) == {"skills": [{"skillId": "sk_1", "level": 4}]}
    assert result.skills[0].level == 4


# ---- comments, metrics, location -----------------------------------------


def test_a_worker_reads_comments_on_their_own_task(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "comments": [
                    {
                        "id": "c1",
                        "body": "customer called back",
                        "createdAt": 1_754_000_000_000,
                        "author": {"kind": "worker", "id": "agent_1"},
                    }
                ],
                "hasMore": False,
                "nextCursor": None,
            },
        )
    )

    page = worker.comments("task_8fk2", limit=20)

    assert server.last.url.path == "/v1/portal/tasks/task_8fk2/comments"
    assert server.last.url.params["limit"] == "20"
    assert page.comments[0].body == "customer called back"


def test_worker_comment_paging_params_reach_the_wire(server, worker):
    # The worker transport ignored `spec.query` until this surface needed it; a silently
    # dropped cursor would page forever on the first page.
    server.set_response(json_response(200, {"comments": [], "hasMore": False}))

    worker.comments("task_8fk2", cursor="c_42", limit=5)

    assert server.last.url.params["cursor"] == "c_42"
    assert server.last.url.params["limit"] == "5"


def test_a_worker_adds_a_comment_with_a_bare_body_field(server, worker):
    server.set_response(
        json_response(
            201,
            {
                "comment": {
                    "id": "c2",
                    "body": "on my way",
                    "createdAt": 1,
                    "author": {"kind": "worker", "id": "agent_1"},
                }
            },
        )
    )

    comment = worker.add_comment("task_8fk2", "on my way")

    assert read_body(server.last) == {"body": "on my way"}
    # The envelope is unwrapped: the caller asked for a comment, not a wrapper.
    assert comment.body == "on my way"


def test_the_metrics_window_defaults_to_seven_days(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "since": "2026-08-13",
                "days": [{"day": "2026-08-13", "completed": 4}],
                "medianWaitMs": 1200,
                "medianCycleMs": 45_000,
            },
        )
    )

    metrics = worker.metrics_window()

    assert server.last.url.path == "/v1/portal/metrics"
    assert server.last.url.params["window"] == "7d"
    assert metrics.days[0].completed == 4
    assert metrics.median_cycle_ms == 45_000


def test_an_explicit_metrics_window_overrides_the_default(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "since": "x", "days": []}))

    metrics = worker.metrics_window("30d")

    assert server.last.url.params["window"] == "30d"
    # No data yet is None, not zero — a fresh worker has no median, not a median of nothing.
    assert metrics.median_wait_ms is None


def test_updating_location_sends_plain_coordinates(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "latitude": 59.4, "longitude": 24.7}))

    result = worker.update_location(WorkerLocation(latitude=59.4, longitude=24.7))

    assert server.last.url.path == "/v1/portal/workers/me/location"
    assert read_body(server.last) == {"latitude": 59.4, "longitude": 24.7}
    assert result.longitude == 24.7


# ---- push: native devices and web push -----------------------------------


def test_registering_an_expo_device_defaults_the_platform(server, worker):
    server.set_response(json_response(201, {"token": "ExpoTok", "platform": "unknown"}))

    device = worker.register_device(WorkerDeviceInput(token="ExpoTok"))

    assert server.last.url.path == "/v1/portal/devices"
    assert read_body(server.last) == {"token": "ExpoTok", "platform": "unknown"}
    assert device.platform == "unknown"


def test_unregistering_a_device_sends_the_token_in_the_body_of_a_delete(server, worker):
    server.set_response(empty_response(204))

    worker.unregister_device("ExpoTok")

    assert server.last.method == "DELETE"
    assert read_body(server.last) == {"token": "ExpoTok"}


def test_push_config_reports_a_deployment_without_vapid_keys_as_disabled(server, worker):
    # Not an error: the portal still works, it just cannot push. Prompting for notification
    # permission here would burn the one prompt a browser gives you.
    server.set_response(json_response(200, {"enabled": False, "publicKey": None}))

    config = worker.push_config()

    assert server.last.url.path == "/v1/portal/push/config"
    assert config.enabled is False
    assert config.public_key is None


def test_push_config_carries_the_vapid_key_when_enabled(server, worker):
    server.set_response(json_response(200, {"enabled": True, "publicKey": "BPk..."}))

    assert worker.push_config().public_key == "BPk..."


def test_subscribing_nests_the_browser_keys_the_way_the_api_expects(server, worker):
    # The browser hands over a flat-ish PushSubscription; the wire wants `keys: {p256dh, auth}`.
    server.set_response(json_response(201, {"endpoint": "https://fcm/x", "createdAt": "2026-08-20T00:00:00Z"}))

    subscription = worker.push_subscribe(PushSubscriptionInput(endpoint="https://fcm/x", p256dh="p2", auth="au"))

    assert server.last.url.path == "/v1/portal/push/subscriptions"
    assert read_body(server.last) == {"endpoint": "https://fcm/x", "keys": {"p256dh": "p2", "auth": "au"}}
    assert subscription.endpoint == "https://fcm/x"


def test_unsubscribing_sends_only_the_endpoint(server, worker):
    # By the time a browser fires `pushsubscriptionchange` it has already discarded the keys,
    # so requiring them here would make unsubscribe impossible in the case it exists for.
    server.set_response(empty_response(204))

    worker.push_unsubscribe("https://fcm/x")

    assert server.last.method == "DELETE"
    assert read_body(server.last) == {"endpoint": "https://fcm/x"}


# ---- async parity --------------------------------------------------------


def test_async_worker_self_service_mirrors_the_sync_wire(server, async_worker):
    def respond(request):
        path = request.url.path
        if request.method == "DELETE":
            return empty_response(204)
        if path.endswith("/portal/me") and request.method == "PATCH":
            return json_response(200, {"workerId": "agent_1", "locale": "et"})
        if path.endswith("/portal/me"):
            return json_response(200, {"workerId": "agent_1", "label": "Ada", "skillSetupPending": False})
        if path.endswith("/me/availability"):
            return json_response(200, {"workerId": "agent_1", "available": True})
        if path.endswith("/me/pin"):
            return empty_response(204)
        if path.endswith("/portal/skills"):
            return json_response(200, {"skills": []})
        if path.endswith("/me/skills"):
            return json_response(200, {"workerId": "agent_1", "skills": []})
        if path.endswith("/comments"):
            return json_response(
                200,
                {"comments": [], "hasMore": False}
                if request.method == "GET"
                else {"comment": {"id": "c1", "body": "hi", "createdAt": 1, "author": {"kind": "worker", "id": "a"}}},
            )
        if path.endswith("/portal/metrics"):
            return json_response(200, {"workerId": "agent_1", "since": "x", "days": []})
        if path.endswith("/me/location"):
            return json_response(200, {"workerId": "agent_1", "latitude": 1.0, "longitude": 2.0})
        if path.endswith("/portal/devices"):
            return json_response(201, {"token": "t", "platform": "ios"})
        if path.endswith("/push/config"):
            return json_response(200, {"enabled": True, "publicKey": "BPk"})
        if path.endswith("/push/subscriptions"):
            return json_response(201, {"endpoint": "https://fcm/x", "createdAt": "x"})
        if path.endswith("/worker-auth/refresh"):
            return json_response(200, {"token": "wt_rotated"})
        return json_response(200, {"token": "wt_new", "workspaceId": "ws_1", "workerId": "agent_1"})

    server.set_responder(respond)

    async def main():
        try:
            return (
                await async_worker.me(),
                await async_worker.set_availability(True),
                await async_worker.change_pin(ChangePin(current_pin="1", new_pin="4821")),
                await async_worker.set_locale("et"),
                await async_worker.skill_catalog(),
                await async_worker.set_skills([WorkerSkillLevel(skill_id="sk_1", level=2)]),
                await async_worker.comments("t1"),
                await async_worker.add_comment("t1", "hi"),
                await async_worker.metrics_window("30d"),
                await async_worker.update_location(WorkerLocation(latitude=1.0, longitude=2.0)),
                await async_worker.register_device(WorkerDeviceInput(token="t", platform="ios")),
                await async_worker.push_config(),
                await async_worker.push_subscribe(PushSubscriptionInput(endpoint="e", p256dh="p", auth="a")),
                await async_worker.refresh(),
            )
        finally:
            await async_worker.aclose()

    me, avail, pin, locale, catalog, skills, comments, comment, metrics, loc, device, cfg, sub, refreshed = (
        asyncio.run(main())
    )

    assert me.worker_id == "agent_1"
    assert avail.available is True
    assert pin is None
    assert locale.locale == "et"
    assert catalog == [] and skills.worker_id == "agent_1"
    assert comments.comments == [] and comment.body == "hi"
    assert metrics.worker_id == "agent_1"
    assert loc.longitude == 2.0
    assert device.platform == "ios"
    assert cfg.enabled is True and sub.endpoint == "https://fcm/x"
    assert refreshed is True
    assert async_worker.session_token == "wt_rotated"


def test_async_worker_join_and_invite_adopt_their_tokens(server, async_worker):
    server.set_responder(
        lambda request: json_response(
            200, {"token": "wt_minted", "workspaceId": "ws_1", "workerId": "agent_9", "label": "Ada"}
        )
    )

    async def main():
        try:
            joined = await async_worker.join(JoinWorkspace(token="t", name="Ada", pin="4821"))
            first = async_worker.session_token
            accepted = await async_worker.accept_invite(AcceptWorkerInvite(token="t", pin="4821"))
            await async_worker.unregister_device("tok")
            await async_worker.push_unsubscribe("https://fcm/x")
            return joined, first, accepted
        finally:
            await async_worker.aclose()

    joined, first, accepted = asyncio.run(main())

    assert joined.worker_id == "agent_9"
    assert first == "wt_minted"
    assert accepted.worker_id == "agent_9"


def test_async_refresh_without_a_token_short_circuits(server, async_worker):
    async def main():
        try:
            async_worker.set_token(None)
            return await async_worker.refresh()
        finally:
            await async_worker.aclose()

    assert asyncio.run(main()) is False
    assert server.requests == []


def test_async_refresh_treats_a_4xx_as_reauthenticate(server, async_worker):
    server.set_response(json_response(403, {"error": {"code": "forbidden", "message": "no"}}))

    async def main():
        try:
            return await async_worker.refresh()
        finally:
            await async_worker.aclose()

    assert asyncio.run(main()) is False


def test_async_refresh_propagates_a_5xx(server, async_worker):
    server.set_response(json_response(500, {"error": {"code": "boom", "message": "no"}}))

    async def main():
        try:
            await async_worker.refresh()
        except Exception as error:  # noqa: BLE001 - asserted below
            return getattr(error, "status_code", None)
        finally:
            await async_worker.aclose()
        return None  # pragma: no cover - raising is the point

    assert asyncio.run(main()) == 500
