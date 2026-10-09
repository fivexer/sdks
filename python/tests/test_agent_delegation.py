"""Handing work off and reading it back: the result contract a task is created with, and the
outcome read that long-polls until the work is done."""

from __future__ import annotations

import asyncio
import json

import httpx

from fivexer import (
    CreateTask,
    ResultContract,
    TaskContext,
    TaskOutcome,
    WorkerTaskDetail,
)
from fivexer import _specs as specs
from fivexer.client import _held_timeout

DONE = {
    "id": "job-1",
    "status": "completed",
    "done": True,
    "workerId": "w1",
    "result": {"url": "https://example.com"},
    "terminalAt": 1760000000000,
}


def test_a_task_carries_its_result_contract_on_the_wire():
    spec = specs.tasks_create(
        CreateTask(
            tags=["seo"],
            result_contract=ResultContract(schema={"type": "object", "required": ["url"]}, acceptance_criteria="A URL"),
        )
    )
    assert spec.body["resultContract"] == {
        "schema": {"type": "object", "required": ["url"]},
        "acceptanceCriteria": "A URL",
    }


def test_a_contract_sends_only_what_was_set_and_a_task_without_one_sends_none():
    with_criteria = specs.tasks_create(
        CreateTask(tags=["seo"], result_contract=ResultContract(acceptance_criteria="x"))
    )
    assert with_criteria.body["resultContract"] == {"acceptanceCriteria": "x"}
    assert "resultContract" not in specs.tasks_create(CreateTask(tags=["seo"])).body


def test_the_outcome_long_polls_with_wait_ms(server, client):
    server.set_response(httpx.Response(200, json=DONE))

    outcome = client.tasks.outcome("job-1", wait_ms=20000)

    assert server.last.url.path == "/v1/tasks/job-1/outcome"
    assert server.last.url.params["waitMs"] == "20000"
    assert outcome == TaskOutcome(
        id="job-1",
        status="completed",
        done=True,
        worker_id="w1",
        result={"url": "https://example.com"},
        terminal_at=1760000000000,
    )


def test_a_live_outcome_reads_without_waiting(server, client):
    server.set_response(
        httpx.Response(
            200,
            json={
                "id": "job/3",
                "status": "queued",
                "done": False,
                "workerId": None,
                "result": None,
                "terminalAt": None,
            },
        )
    )

    outcome = client.tasks.outcome("job/3")

    assert server.last.url.raw_path == b"/v1/tasks/job%2F3/outcome"
    assert "waitMs" not in server.last.url.params
    assert (outcome.done, outcome.worker_id, outcome.result, outcome.terminal_at) == (False, None, None, None)


def test_the_async_client_reads_the_outcome_too(server, async_client):
    server.set_response(httpx.Response(200, json=DONE))

    outcome = asyncio.run(async_client.tasks.outcome("job-1", wait_ms=5))

    assert server.last.url.params["waitMs"] == "5"
    assert outcome.done is True


def test_the_context_and_the_worker_detail_read_back_the_contract():
    contract = {"schema": {"type": "object"}, "acceptanceCriteria": "A URL"}
    context = TaskContext.from_json({"taskId": "t", "references": [], "resultContract": contract})
    assert context.result_contract == ResultContract(schema={"type": "object"}, acceptance_criteria="A URL")
    assert TaskContext.from_json({"taskId": "t", "references": []}).result_contract is None

    detail = WorkerTaskDetail.from_json({"id": "t", "status": "pending", "resultContract": contract})
    assert detail.result_contract is not None
    assert detail.result_contract.acceptance_criteria == "A URL"
    assert json.dumps(detail.result_contract.to_json(), sort_keys=True) == json.dumps(contract, sort_keys=True)


def test_a_long_poll_gets_a_read_timeout_that_outlasts_its_wait(server, client):
    seen: list[dict[str, float | None]] = []

    def respond(request: httpx.Request) -> httpx.Response:
        seen.append(request.extensions["timeout"])
        return httpx.Response(200, json=DONE)

    server.set_responder(respond)
    client.tasks.outcome("job-1", wait_ms=25000)
    client.tasks.outcome("job-1")

    assert seen[0]["read"] == 35.0
    # Without a wait the client's own timeout stands.
    assert seen[1]["read"] == client._client.timeout.read


def test_a_patient_client_keeps_its_own_timeout():
    assert _held_timeout(httpx.Timeout(60.0), 25000) is None
    assert _held_timeout(httpx.Timeout(5.0, read=None), 25000) is None
    assert _held_timeout(httpx.Timeout(5.0), None) is None
    assert _held_timeout(httpx.Timeout(5.0), 1000) == httpx.Timeout(connect=5.0, read=11.0, write=5.0, pool=5.0)


def test_the_async_client_extends_the_read_timeout_too(server, async_client):
    seen: list[dict[str, float | None]] = []

    def respond(request: httpx.Request) -> httpx.Response:
        seen.append(request.extensions["timeout"])
        return httpx.Response(200, json=DONE)

    server.set_responder(respond)
    asyncio.run(async_client.tasks.outcome("job-1", wait_ms=25000))
    assert seen[0]["read"] == 35.0
