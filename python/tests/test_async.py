"""Async client: full lifecycle exercising every async resource method.

Black-box parity check that AsyncFivexer mirrors the sync client's wire behavior for the
complete tasks/workers/decisions surface, plus the async retry and 204 paths.
"""

from __future__ import annotations

import asyncio
import json
from typing import Any

import httpx
import pytest

from fivexer import AsyncFivexer, CreateTask, ListTasksQuery, UpsertWorker


def _client(handler) -> AsyncFivexer:
    return AsyncFivexer(
        base_url="https://api.fivexer.test",
        api_key="sk_test_async",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
    )


def _route(request: httpx.Request) -> httpx.Response:
    """A canned responder that answers every /v1 route sensibly."""
    path = request.url.path
    if path == "/v1/tasks" and request.method == "POST":
        body = json.loads(request.content)
        return httpx.Response(202, json={"id": body.get("id", "task_1"), "status": "queued"})
    if path == "/v1/tasks" and request.method == "GET":
        return httpx.Response(
            200,
            json={
                "tasks": [
                    {
                        "id": "task_1",
                        "tags": ["x"],
                        "priority": 1,
                        "status": "queued",
                        "workerId": None,
                        "createdAt": 1,
                        "meta": None,
                    }
                ],
                "nextCursor": None,
                "hasMore": False,
            },
        )
    if path.startswith("/v1/tasks/") and path.endswith("/accept"):
        return httpx.Response(200, json={"id": "task_1", "status": "accepted"})
    if path.startswith("/v1/tasks/") and path.endswith("/reject"):
        return httpx.Response(200, json={"id": "task_1", "status": "queued"})
    if path.startswith("/v1/tasks/") and path.endswith("/complete"):
        return httpx.Response(200, json={"id": "task_1", "status": "completed"})
    if path == "/v1/workers" and request.method == "POST":
        return httpx.Response(200, json={"id": "agent_1"})
    if path == "/v1/workers" and request.method == "GET":
        return httpx.Response(200, json={"workers": ["agent_1"], "count": 1})
    if path.endswith("/queue"):
        return httpx.Response(200, json={"workerId": "agent_1", "taskIds": ["task_1"]})
    if request.method == "DELETE":
        return httpx.Response(204)
    if path.startswith("/v1/tasks/"):
        return httpx.Response(
            200,
            json={
                "id": "task_1",
                "tags": ["x"],
                "priority": 1,
                "status": "accepted",
                "workerId": "agent_1",
                "createdAt": 1,
                "meta": None,
            },
        )
    return httpx.Response(404, json={"error": {"code": "not_found", "message": "?"}})


def test_async_full_task_and_worker_lifecycle_round_trips_every_endpoint():
    async def main() -> dict[str, Any]:
        client = _client(_route)
        worker_id = await client.workers.upsert(UpsertWorker(id="agent_1", tags=["english"]))
        worker_list = await client.workers.list()
        queue = await client.workers.queue("agent_1")
        created = await client.tasks.create(CreateTask(id="task_1", tags=["english"]))
        fetched = await client.tasks.get("task_1")
        listed = await client.tasks.list(ListTasksQuery(status="queued"))
        accepted = await client.tasks.accept("task_1", "agent_1")
        rejected = await client.tasks.reject("task_1", "agent_1")
        completed = await client.tasks.complete("task_1", "agent_1", result={"ok": True})
        await client.tasks.cancel("task_1")
        await client.workers.remove("agent_1")
        await client.aclose()
        return dict(
            worker_id=worker_id,
            workers=worker_list.workers,
            queue=queue.task_ids,
            created=created.id,
            fetched=fetched.status,
            listed=len(listed.tasks),
            accepted=accepted.status,
            rejected=rejected.status,
            completed=completed.status,
        )

    result = asyncio.run(main())
    assert result == dict(
        worker_id="agent_1",
        workers=["agent_1"],
        queue=["task_1"],
        created="task_1",
        fetched="accepted",
        listed=1,
        accepted="accepted",
        rejected="queued",
        completed="completed",
    )


def test_async_retry_then_success_on_5xx():
    async def main():
        calls = {"n": 0}

        def handler(request: httpx.Request) -> httpx.Response:
            calls["n"] += 1
            if calls["n"] == 1:
                return httpx.Response(503, json={"error": {"code": "internal_error", "message": "down"}})
            return httpx.Response(200, json={"workers": ["a"], "count": 1})

        client = AsyncFivexer(
            base_url="https://api.fivexer.test",
            api_key="k",
            max_retries=1,
            http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
        )
        result = await client.workers.list()
        await client.aclose()
        return result, calls["n"]

    result, n = asyncio.run(main())
    assert n == 2
    assert result.count == 1


def test_async_204_returns_none():
    async def main():
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(204)

        client = _client(handler)
        res = await client.tasks.cancel("task_1")
        await client.aclose()
        return res

    assert asyncio.run(main()) is None


def test_async_upsert_without_argument_defaults_to_empty_body():
    async def main():
        seen: dict[str, Any] = {}

        def handler(request: httpx.Request) -> httpx.Response:
            seen["body"] = json.loads(request.content) if request.content else {}
            return httpx.Response(200, json={"id": "w_1"})

        client = _client(handler)
        wid = await client.workers.upsert()
        await client.aclose()
        return wid, seen["body"]

    wid, body = asyncio.run(main())
    assert wid == "w_1"
    assert body == {}


def test_async_context_manager_closes_the_client():
    async def main():
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                200,
                json={
                    "plan": "free",
                    "tasks": {},
                    "workers": 0,
                    "meter": {"period": "p", "matchedTasks": 0, "includedTasksPerMonth": 0},
                },
            )

        async with AsyncFivexer(
            base_url="https://api.fivexer.test",
            api_key="k",
            http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
        ) as client:
            stats = await client.stats()
        return stats.plan

    assert asyncio.run(main()) == "free"


def test_create_rejects_a_non_createtask_argument(server, client):
    from tests.conftest import json_response

    server.set_response(json_response(202, {"id": "t", "status": "queued"}))
    with pytest.raises(TypeError):
        client.tasks.create({"tags": ["x"]})  # type: ignore[arg-type]


def test_upsert_rejects_a_non_upsertworker_argument(server, client):
    from tests.conftest import json_response

    server.set_response(json_response(200, {"id": "w"}))
    with pytest.raises(TypeError):
        client.workers.upsert({"id": "w"})  # type: ignore[arg-type]
