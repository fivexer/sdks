"""Error handling, quota-header parsing, and transport retry behavior."""

from __future__ import annotations

import pytest

from fivexer import FivexerApiError
from tests.conftest import empty_response, json_response


def test_quota_headers_on_a_successful_response_are_exposed_on_the_client(server, client):
    server.set_response(
        json_response(
            202,
            {"id": "task_8fk2", "status": "queued"},
            headers={
                "x-quota-task-rate-limit": "300",
                "x-quota-task-rate-remaining": "299",
                "x-quota-queued-tasks-limit": "50000",
                "x-quota-queued-tasks-remaining": "49999",
            },
        )
    )
    from fivexer import CreateTask

    client.tasks.create(CreateTask(tags=["english"]))

    assert client.quota is not None
    assert client.quota.task_rate_limit == 300
    assert client.quota.task_rate_remaining == 299
    assert client.quota.queued_tasks_limit == 50000
    assert client.quota.queued_tasks_remaining == 49999
    assert client.quota.workers_limit is None


def test_a_rate_limited_request_raises_with_retry_after_and_quota_attached(server, client):
    server.set_response(
        json_response(
            429,
            {"error": {"code": "rate_limited", "message": "task creation rate above 300/min"}},
            headers={"retry-after": "60", "x-quota-task-rate-limit": "300", "x-quota-task-rate-remaining": "0"},
        )
    )
    from fivexer import CreateTask

    with pytest.raises(FivexerApiError) as exc:
        client.tasks.create(CreateTask(tags=["x"]))
    assert exc.value.status_code == 429
    assert exc.value.code == "rate_limited"
    assert exc.value.retry_after == 60.0
    assert exc.value.quota is not None
    assert exc.value.quota.task_rate_remaining == 0


def test_a_validation_error_reports_the_code_and_message(server, client):
    server.set_response(
        json_response(
            400,
            {"error": {"code": "validation_failed", "message": "body must have required property 'tags'"}},
        )
    )
    from fivexer import CreateTask

    with pytest.raises(FivexerApiError) as exc:
        client.tasks.create(CreateTask(tags=[]))  # tags=[] is invalid server-side; SDK just sends it
    assert exc.value.status_code == 400
    assert exc.value.code == "validation_failed"
    assert "tags" in exc.value.args[0]


def test_an_unparseable_error_body_falls_back_to_unknown_code(server, client):
    server.set_response(json_response(500, "<html>oops</html>"))

    with pytest.raises(FivexerApiError) as exc:
        client.tasks.list()
    assert exc.value.status_code == 500
    assert exc.value.code == "unknown_error"


def test_a_204_response_has_no_body(server, client):
    server.set_response(empty_response(204))
    assert client.tasks.cancel("task_8fk2") is None


def test_retry_transparently_retries_once_on_5xx_then_succeeds(server):
    import httpx

    from fivexer import Fivexer

    call_count = {"n": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        call_count["n"] += 1
        if call_count["n"] == 1:
            return json_response(503, {"error": {"code": "internal_error", "message": "transient"}})
        return json_response(200, {"tasks": [], "nextCursor": None, "hasMore": False})

    client = Fivexer(
        base_url="https://api.fivexer.test",
        api_key="sk_test_x",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    page = client.tasks.list()
    assert call_count["n"] == 2
    assert page.has_more is False
    client.close()


def test_a_retried_post_sends_the_same_idempotency_key_both_times():
    import httpx

    from fivexer import CreateTask, Fivexer

    received_keys: list[str | None] = []

    def handler(request: httpx.Request) -> httpx.Response:
        received_keys.append(request.headers.get("idempotency-key"))
        if len(received_keys) == 1:
            return json_response(503, {"error": {"code": "internal_error", "message": "transient"}})
        return json_response(202, {"id": "task_1", "status": "queued"})

    client = Fivexer(
        base_url="https://api.fivexer.test",
        api_key="sk_test_x",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    client.tasks.create(CreateTask(tags=["english"]))
    client.close()

    assert len(received_keys) == 2
    assert received_keys[0] is not None, "first POST must carry an idempotency key"
    assert received_keys[0] == received_keys[1], "idempotency key must be stable across retries"


def test_retry_respects_retry_after_with_no_sleep_when_zero(server):
    import httpx

    from fivexer import Fivexer

    calls = {"n": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        calls["n"] += 1
        if calls["n"] == 1:
            return json_response(429, {"error": {"code": "rate_limited", "message": "slow down"}},
                                 headers={"retry-after": "0"})
        return json_response(202, {"id": "task_1", "status": "queued"})

    from fivexer import CreateTask

    client = Fivexer(
        base_url="https://api.fivexer.test",
        api_key="sk_test_x",
        max_retries=1,
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    task = client.tasks.create(CreateTask(tags=["x"]))
    assert task.id == "task_1"
    assert calls["n"] == 2
    client.close()


def test_retry_does_not_retry_when_max_retries_is_zero(server, client):
    # client fixture has max_retries=0; a 503 should surface immediately
    server.set_response(json_response(503, {"error": {"code": "internal_error", "message": "down"}}))

    with pytest.raises(FivexerApiError) as exc:
        client.tasks.list()
    assert exc.value.status_code == 503
    assert len(server.requests) == 1


def test_constructor_rejects_missing_base_url_and_key():
    from fivexer import Fivexer

    with pytest.raises(ValueError):
        Fivexer(base_url="", api_key="sk_x")
    with pytest.raises(ValueError):
        Fivexer(base_url="https://api.fivexer.test", api_key="")
