"""Task lifecycle: creating, reading, listing, cancelling, and transitioning tasks.

Black-box: drive ``client.tasks.*`` and assert the observable result plus the exact HTTP
the SDK emits against the /v1 contract.
"""

from __future__ import annotations

import uuid

import httpx
import pytest

from fivexer import CreateTask, FivexerApiError, ListTasksQuery
from tests.conftest import empty_response, json_response, query_pairs, read_body


def test_creating_a_task_with_tags_returns_queued_task(server, client):
    server.set_response(json_response(202, {"id": "task_8fk2", "status": "queued"}))

    task = client.tasks.create(CreateTask(tags=["english", "billing"]))

    assert task.id == "task_8fk2"
    assert task.status == "queued"
    req = server.last
    assert req.method == "POST"
    assert req.url.path == "/v1/tasks"
    assert read_body(req) == {"tags": ["english", "billing"]}
    assert req.headers["authorization"] == "Bearer sk_test_abc123"


def test_creating_a_task_auto_attaches_an_idempotency_key(server, client):
    server.set_response(json_response(202, {"id": "task_8fk2", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["english"]))

    key = server.last.headers.get("idempotency-key")
    assert key is not None
    uuid.UUID(key)  # raises if not a valid UUID


def test_creating_a_task_with_priority_meta_and_vetoes_serializes_every_field(server, client):
    server.set_response(json_response(202, {"id": "task_9", "status": "queued"}))

    client.tasks.create(
        CreateTask(
            id="task_9",
            tags=["english"],
            priority=90,
            meta={"ticketId": "T-441"},
            vetoed_workers=["w_123"],
            skill_thresholds={"english": 5},
        )
    )

    assert read_body(server.last) == {
        "id": "task_9",
        "tags": ["english"],
        "priority": 90,
        "meta": {"ticketId": "T-441"},
        "vetoedWorkers": ["w_123"],
        "skillThresholds": {"english": 5},
    }


def test_getting_a_task_returns_parsed_fields(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_8fk2",
                "tags": ["english", "billing"],
                "priority": 90,
                "status": "pending",
                "workerId": "agent_1",
                "createdAt": 1750000000000,
                "meta": {"ticketId": "T-441"},
            },
        )
    )

    task = client.tasks.get("task_8fk2")

    assert task.id == "task_8fk2"
    assert task.tags == ["english", "billing"]
    assert task.priority == 90
    assert task.status == "pending"
    assert task.worker_id == "agent_1"
    assert task.created_at == 1750000000000
    assert task.meta == {"ticketId": "T-441"}
    assert server.last.url.path == "/v1/tasks/task_8fk2"


def test_getting_a_missing_task_raises_not_found(server, client):
    server.set_response(json_response(404, {"error": {"code": "not_found", "message": "task not found"}}))

    with pytest.raises(FivexerApiError) as exc:
        client.tasks.get("missing")
    assert exc.value.status_code == 404
    assert exc.value.code == "not_found"


def test_listing_tasks_passes_status_cursor_and_limit_as_query(server, client):
    server.set_response(json_response(200, {"tasks": [], "nextCursor": None, "hasMore": False}))

    client.tasks.list(ListTasksQuery(status="queued", cursor="c1", limit=50))

    assert query_pairs(server.last) == {"status": "queued", "cursor": "c1", "limit": "50"}


def test_listing_tasks_paginates_and_parses_cursor(server, client):
    server.set_response(
        json_response(
            200,
            {
                "tasks": [{"id": "t1", "tags": ["x"], "priority": 1, "status": "queued", "workerId": None,
                           "createdAt": 1, "meta": None}],
                "nextCursor": "cursor_1",
                "hasMore": True,
            },
        )
    )

    page = client.tasks.list()

    assert page.has_more is True
    assert page.next_cursor == "cursor_1"
    assert page.tasks[0].id == "t1"


def test_cancelling_a_task_returns_nothing(server, client):
    server.set_response(empty_response(204))

    result = client.tasks.cancel("task_8fk2")

    assert result is None
    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/tasks/task_8fk2"


def test_a_worker_accepts_a_pending_task(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "accepted"}))

    result = client.tasks.accept("task_8fk2", "agent_1")

    assert (result.id, result.status) == ("task_8fk2", "accepted")
    assert read_body(server.last) == {"workerId": "agent_1"}
    assert server.last.url.path == "/v1/tasks/task_8fk2/accept"


def test_a_worker_rejects_a_task_so_it_is_requeued(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "queued"}))

    result = client.tasks.reject("task_8fk2", "agent_1")

    assert result.status == "queued"
    assert server.last.url.path == "/v1/tasks/task_8fk2/reject"


def test_a_worker_completes_a_task_with_a_result(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "completed"}))

    result = client.tasks.complete("task_8fk2", "agent_1", result={"resolved": True})

    assert result.status == "completed"
    assert read_body(server.last) == {"workerId": "agent_1", "result": {"resolved": True}}


def test_completing_a_task_without_a_result_omits_the_field(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "completed"}))

    client.tasks.complete("task_8fk2", "agent_1")

    assert read_body(server.last) == {"workerId": "agent_1"}


def test_base_url_trailing_slash_is_stripped(server):
    transport = httpx.MockTransport(lambda r: json_response(200, {"tasks": [], "nextCursor": None, "hasMore": False}))
    from fivexer import Fivexer

    client = Fivexer(
        base_url="https://api.fivexer.test/",
        api_key="sk_test_x",
        http_client=httpx.Client(transport=transport),
    )
    assert client.base_url == "https://api.fivexer.test"
    client.close()
