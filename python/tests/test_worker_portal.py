"""The worker portal plane: one worker acting on their own queue with a `wt_` token.

The security property this plane exists for is that the token is scoped to a single worker and
cannot reach task creation or worker management. These tests hold the client to its half of
that: it never sends a workspace key, and it will not guess a worker id it was not given.
"""

from __future__ import annotations

import pytest

from fivexer import FivexerApiError, FivexerWorker, WorkerLogin
from tests.conftest import empty_response, json_response, read_body

# ---- login / logout -------------------------------------------------------


def test_logging_in_adopts_the_token_and_the_worker_id(server, anon_worker):
    server.set_response(json_response(200, {"token": "wt_s3ss10n"}))

    session = anon_worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

    assert session.token == "wt_s3ss10n"
    assert anon_worker.session_token == "wt_s3ss10n"
    assert anon_worker.worker_id == "agent_1"
    assert read_body(server.last) == {"workspaceId": "ws_1", "workerId": "agent_1", "pin": "4821"}


def test_the_login_request_itself_carries_no_authorization(server, anon_worker):
    # There is no token yet; sending an empty bearer would be a malformed request.
    server.set_response(json_response(200, {"token": "wt_s3ss10n"}))

    anon_worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

    assert "authorization" not in server.last.headers


def test_a_wrong_pin_leaves_the_client_unauthenticated(server, anon_worker):
    server.set_response(
        json_response(401, {"error": {"code": "invalid_credentials", "message": "invalid worker credentials"}})
    )

    with pytest.raises(FivexerApiError) as excinfo:
        anon_worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="0000"))

    assert excinfo.value.code == "invalid_credentials"
    assert anon_worker.session_token is None


def test_logging_out_forgets_the_token(server, worker):
    server.set_response(empty_response(204))

    worker.logout()

    assert worker.session_token is None


def test_a_resumed_session_authenticates_with_the_stored_token(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "taskIds": []}))

    worker.queue()

    assert server.last.headers["authorization"] == "Bearer wt_s3ss10n"


def test_a_token_can_be_adopted_after_construction(server, anon_worker):
    anon_worker.set_token("wt_restored", "agent_7")
    server.set_response(json_response(200, {"workerId": "agent_7", "taskIds": []}))

    anon_worker.queue()

    assert server.last.url.path == "/v1/portal/workers/agent_7/queue"
    assert server.last.headers["authorization"] == "Bearer wt_restored"


def test_adopting_a_token_without_a_worker_id_keeps_the_existing_one(server, worker):
    worker.set_token("wt_rotated")
    server.set_response(json_response(200, {"workerId": "agent_1", "taskIds": []}))

    worker.queue()

    assert server.last.url.path == "/v1/portal/workers/agent_1/queue"


# ---- acting without a worker id ------------------------------------------


def test_acting_before_logging_in_fails_without_reaching_the_network(server, anon_worker):
    # Guessing an id here would let one worker act as another; refusing locally is the point.
    with pytest.raises(FivexerApiError) as excinfo:
        anon_worker.queue()

    assert excinfo.value.code == "worker_id_required"
    assert server.requests == []


def test_an_explicit_worker_id_works_without_a_prior_login(server, anon_worker):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "accepted"}))

    anon_worker.accept("task_8fk2", worker_id="agent_9")

    assert read_body(server.last) == {"workerId": "agent_9"}


# ---- queue and task actions ----------------------------------------------


def test_a_worker_reads_their_own_queue(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "taskIds": ["task_8fk2"]}))

    queue = worker.queue()

    assert server.last.url.path == "/v1/portal/workers/agent_1/queue"
    assert queue.task_ids == ["task_8fk2"]


def test_task_detail_includes_the_rich_context_the_worker_needs(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_8fk2",
                "status": "pending",
                "tags": ["english", "billing"],
                "priority": 90,
                "title": "Refund request",
                "description": "Customer wants a refund for order 41",
                "context": {"orderId": "41"},
                "references": [{"id": "ref_1", "url": "https://crm/o/41", "label": "Order 41"}],
                "createdAt": 1750000000000,
            },
        )
    )

    detail = worker.task_detail("task_8fk2")

    assert detail.title == "Refund request"
    assert detail.references is not None
    assert detail.references[0].label == "Order 41"


def test_another_workers_task_is_not_visible(server, worker):
    server.set_response(json_response(404, {"error": {"code": "not_found", "message": "task not found"}}))

    with pytest.raises(FivexerApiError) as excinfo:
        worker.task_detail("task_other")

    assert excinfo.value.status_code == 404


def test_accepting_a_task_reports_the_new_status(server, worker):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "accepted"}))

    result = worker.accept("task_8fk2")

    assert server.last.url.path == "/v1/portal/tasks/task_8fk2/accept"
    assert read_body(server.last) == {"workerId": "agent_1"}
    assert (result.id, result.status) == ("task_8fk2", "accepted")


def test_rejecting_a_task_requeues_it_for_others(server, worker):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "queued"}))

    assert worker.reject("task_8fk2").status == "queued"
    assert server.last.url.path == "/v1/portal/tasks/task_8fk2/reject"


def test_completing_a_task_can_attach_a_result(server, worker):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "completed"}))

    assert worker.complete("task_8fk2", {"refunded": True}).status == "completed"
    assert read_body(server.last) == {"workerId": "agent_1", "result": {"refunded": True}}


def test_completing_without_a_result_omits_the_field(server, worker):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "completed"}))

    worker.complete("task_8fk2")

    assert read_body(server.last) == {"workerId": "agent_1"}


# ---- breaks ---------------------------------------------------------------


def test_starting_a_break_pauses_routing_to_this_worker(server, worker):
    server.set_response(
        json_response(200, {"workerId": "agent_1", "onBreak": True, "since": "2026-07-24T11:30:00.000Z"})
    )

    started = worker.start_break("lunch")

    assert server.last.url.path == "/v1/portal/breaks/start"
    assert read_body(server.last) == {"reason": "lunch"}
    assert started.on_break is True


def test_starting_a_second_break_is_refused(server, worker):
    server.set_response(
        json_response(409, {"error": {"code": "break_already_open", "message": "a break is already open"}})
    )

    with pytest.raises(FivexerApiError) as excinfo:
        worker.start_break()

    assert excinfo.value.status_code == 409


def test_ending_a_break_resumes_routing(server, worker):
    server.set_response(
        json_response(200, {"workerId": "agent_1", "onBreak": False, "endedAt": "2026-07-24T12:00:00.000Z"})
    )

    ended = worker.end_break()

    assert ended is not None
    assert ended.on_break is False
    assert ended.ended_at == "2026-07-24T12:00:00.000Z"


def test_ending_a_break_when_none_is_open_is_not_an_error(server, worker):
    # The API answers 204; a portal UI calling this on a timer must not see an exception.
    server.set_response(empty_response(204))

    assert worker.end_break() is None


def test_todays_breaks_include_the_one_still_running(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "since": "2026-07-24T00:00:00.000Z",
                "breaks": [],
                "active": {
                    "id": "brk_2",
                    "startedAt": "2026-07-24T13:00:00.000Z",
                    "endedAt": None,
                    "reason": None,
                    "durationMs": 0,
                },
                "completedTasks": 7,
                "totalBreakMs": 0,
            },
        )
    )

    today = worker.breaks_today()

    assert today.active is not None
    assert today.active.id == "brk_2"
    assert today.completed_tasks == 7


def test_todays_metrics_separate_working_time_from_break_time(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "since": "2026-07-24T00:00:00.000Z",
                "completedTasks": 7,
                "breakCount": 1,
                "totalBreakMs": 1800000,
                "longestBreakMs": 1800000,
                "workingMs": 25200000,
            },
        )
    )

    metrics = worker.metrics_today()

    assert server.last.url.path == "/v1/portal/metrics/today"
    assert metrics.working_ms == 25200000
    assert metrics.total_break_ms == 1800000


def test_a_worker_can_see_who_else_is_on_shift(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workers": [{"workerId": "agent_1", "label": "Ada", "status": "working"}],
                "counts": {"working": 1, "onBreak": 0, "paused": 0, "total": 1},
            },
        )
    )

    presence = worker.team_presence()

    assert server.last.url.path == "/v1/portal/team/presence"
    assert presence.counts.working == 1


# ---- transport behaviour --------------------------------------------------


def test_a_portal_action_is_not_replayed_after_a_server_error(server):
    # Portal actions carry no idempotency key, so retrying an accept could double-accept.
    calls = {"n": 0}

    def handler(request):
        calls["n"] += 1
        return json_response(503, {"error": {"code": "internal_error", "message": "boom"}})

    import httpx

    client = FivexerWorker(
        base_url="https://api.fivexer.test",
        token="wt_s3ss10n",
        worker_id="agent_1",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    server.set_responder(handler)

    with pytest.raises(FivexerApiError):
        client.accept("task_8fk2")

    assert calls["n"] == 1


def test_a_transient_read_failure_is_retried(server):
    import httpx

    calls = {"n": 0}

    def handler(request):
        calls["n"] += 1
        if calls["n"] == 1:
            return json_response(503, {"error": {"code": "internal_error", "message": "boom"}})
        return json_response(200, {"workerId": "agent_1", "taskIds": []})

    client = FivexerWorker(
        base_url="https://api.fivexer.test",
        token="wt_s3ss10n",
        worker_id="agent_1",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    assert client.queue().worker_id == "agent_1"
    assert calls["n"] == 2


def test_a_base_url_is_required():
    with pytest.raises(ValueError):
        FivexerWorker(base_url="")


def test_a_trailing_slash_in_the_base_url_does_not_double_up(server):
    import httpx

    client = FivexerWorker(
        base_url="https://api.fivexer.test/",
        token="wt_1",
        worker_id="agent_1",
        max_retries=0,
        http_client=httpx.Client(transport=httpx.MockTransport(server.handler)),
    )
    server.set_response(json_response(200, {"workerId": "agent_1", "taskIds": []}))

    client.queue()

    assert str(server.last.url) == "https://api.fivexer.test/v1/portal/workers/agent_1/queue"


def test_the_client_closes_the_transport_it_created():
    client = FivexerWorker(base_url="https://api.fivexer.test")
    with client:
        pass

    assert client.base_url == "https://api.fivexer.test"


def test_an_unparseable_error_body_still_raises_a_structured_error(server, worker):
    import httpx

    server.set_response(httpx.Response(500, content=b"<html>gateway</html>"))

    with pytest.raises(FivexerApiError) as excinfo:
        worker.queue()

    assert excinfo.value.code == "unknown_error"
    assert excinfo.value.status_code == 500


def test_a_portal_read_waits_the_retry_after_interval_before_retrying(server):
    import httpx

    calls = {"n": 0}

    def handler(request):
        calls["n"] += 1
        if calls["n"] == 1:
            return httpx.Response(
                503, json={"error": {"code": "busy", "message": "b"}}, headers={"retry-after": "0.001"}
            )
        return json_response(200, {"workerId": "agent_1", "taskIds": []})

    client = FivexerWorker(
        base_url="https://api.fivexer.test",
        token="wt_1",
        worker_id="agent_1",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    assert client.queue().worker_id == "agent_1"
    assert calls["n"] == 2
