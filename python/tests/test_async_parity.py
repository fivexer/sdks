"""Every resource group, driven through ``AsyncFivexer`` / ``AsyncFivexerWorker``.

The async clients are a second full surface, not a thin wrapper — a method missing there is a
real gap for anyone on asyncio. Each test here walks one group end to end and asserts the same
observable behaviour the sync tests assert, so the two surfaces cannot silently diverge.
"""

from __future__ import annotations

import asyncio

import httpx
import pytest

from fivexer import (
    AddComment,
    AsyncFivexer,
    AsyncFivexerWorker,
    CreateAttachment,
    CreateNotificationChannel,
    CreateNotificationSequence,
    CreateTask,
    FivexerApiError,
    LearningFeedbackItem,
    ListDecisionsQuery,
    ListRunsQuery,
    PatchSkill,
    PatchWorker,
    SetTaskContext,
    StartRun,
    StatsWindowQuery,
    SuggestWorkers,
    UpdateNotificationChannel,
    UpdateNotificationSequence,
    UpsertSkill,
    UpsertWorker,
    WorkerLogin,
    WorkflowDefinitionInput,
    WorkflowStep,
)
from tests.conftest import json_response

BASE = "https://api.fivexer.test"


def _async_client(handler) -> AsyncFivexer:
    return AsyncFivexer(
        base_url=BASE,
        api_key="sk_test_abc123",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
    )


def _async_worker(handler) -> AsyncFivexerWorker:
    return AsyncFivexerWorker(
        base_url=BASE,
        token="wt_s3ss10n",
        worker_id="agent_1",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
    )


class Recorder:
    """Answers by ``(method, path suffix)`` and records what was asked."""

    def __init__(self, routes: dict[tuple[str, str], dict | None]):
        self.routes = routes
        self.seen: list[httpx.Request] = []

    def __call__(self, request: httpx.Request) -> httpx.Response:
        self.seen.append(request)
        path = request.url.path.removeprefix("/v1")
        for (method, suffix), body in self.routes.items():
            if request.method == method and path == suffix:
                return httpx.Response(204) if body is None else json_response(200, body)
        return json_response(500, {"error": {"code": "unrouted", "message": path}})

    @property
    def paths(self) -> list[str]:
        return [r.url.path for r in self.seen]


TASK = {"id": "task_1", "tags": ["english"], "priority": 90, "status": "pending", "workerId": "agent_1"}
RUN = {
    "id": "run_1",
    "workflowId": "wf_1",
    "status": "active",
    "initiatorWorkerId": "agent_1",
    "definitionVersion": 1,
    "createdAt": 1,
    "updatedAt": 2,
}
ATTACHMENT = {
    "id": "att_1",
    "taskId": "task_1",
    "filename": "a.pdf",
    "contentType": "application/pdf",
    "sizeBytes": 6,
    "status": "ready",
    "uploader": {"type": "api", "id": None},
    "createdAt": 1,
}


def test_async_task_operations_cover_the_whole_lifecycle():
    recorder = Recorder(
        {
            ("POST", "/tasks"): {"id": "task_1", "status": "queued"},
            ("GET", "/tasks/task_1"): TASK,
            ("GET", "/tasks"): {"tasks": [TASK], "nextCursor": None, "hasMore": False},
            ("DELETE", "/tasks/task_1"): None,
            ("POST", "/tasks/task_1/accept"): {"id": "task_1", "status": "accepted"},
            ("POST", "/tasks/task_1/reject"): {"id": "task_1", "status": "queued"},
            ("POST", "/tasks/task_1/complete"): {"id": "task_1", "status": "completed"},
            ("POST", "/tasks/task_1/assign"): {
                "id": "task_1",
                "status": "pending",
                "workerId": "agent_2",
                "previousWorkerId": "agent_1",
            },
            ("PATCH", "/tasks/task_1"): {"id": "task_1", "priority": 95},
            ("POST", "/tasks/suggest-workers"): {"tags": ["english"], "priority": 50, "workers": []},
        }
    )

    async def main():
        client = _async_client(recorder)
        created = await client.tasks.create(CreateTask(tags=["english"]))
        fetched = await client.tasks.get("task_1")
        page = await client.tasks.list()
        accepted = await client.tasks.accept("task_1", "agent_1")
        rejected = await client.tasks.reject("task_1", "agent_1")
        completed = await client.tasks.complete("task_1", "agent_1", {"ok": True})
        assigned = await client.tasks.assign("task_1", "agent_2", force=True)
        priority = await client.tasks.set_priority("task_1", 95)
        suggestion = await client.tasks.suggest_workers(SuggestWorkers(tags=["english"]))
        await client.tasks.cancel("task_1")
        await client.aclose()
        return created, fetched, page, accepted, rejected, completed, assigned, priority, suggestion

    created, fetched, page, accepted, rejected, completed, assigned, priority, suggestion = asyncio.run(main())

    assert created.status == "queued"
    assert fetched.worker_id == "agent_1"
    assert len(page.tasks) == 1
    assert (accepted.status, rejected.status, completed.status) == ("accepted", "queued", "completed")
    assert assigned.previous_worker_id == "agent_1"
    assert priority.priority == 95
    assert suggestion.workers == []


def test_async_task_rich_data_operations_round_trip():
    context = {"taskId": "task_1", "title": "T", "description": None, "context": None, "references": []}
    recorder = Recorder(
        {
            ("GET", "/tasks/task_1/context"): context,
            ("PUT", "/tasks/task_1/context"): context,
            ("DELETE", "/tasks/task_1/context"): None,
            ("POST", "/tasks/task_1/comments"): {
                "comment": {
                    "id": "c_1",
                    "taskId": "task_1",
                    "author": {"type": "api", "id": None, "label": None},
                    "body": "hi",
                    "createdAt": 1,
                }
            },
            ("GET", "/tasks/task_1/comments"): {"comments": [], "nextCursor": None, "hasMore": False},
            ("DELETE", "/tasks/task_1/comments/c_1"): None,
            ("GET", "/tasks/task_1/attachments"): {"attachments": [ATTACHMENT]},
            ("GET", "/tasks/task_1/attachments/att_1/download"): {"url": "https://s/dl", "expiresAt": 1},
            ("DELETE", "/tasks/task_1/attachments/att_1"): None,
        }
    )

    async def main():
        client = _async_client(recorder)
        got = await client.tasks.context.get("task_1")
        await client.tasks.context.set("task_1", SetTaskContext(title="T"))
        await client.tasks.context.clear("task_1")
        comment = await client.tasks.comments.add("task_1", AddComment(body="hi"))
        comments = await client.tasks.comments.list("task_1")
        await client.tasks.comments.remove("task_1", "c_1")
        attachments = await client.tasks.attachments.list("task_1")
        download = await client.tasks.attachments.download("task_1", "att_1")
        await client.tasks.attachments.remove("task_1", "att_1")
        await client.aclose()
        return got, comment, comments, attachments, download

    got, comment, comments, attachments, download = asyncio.run(main())

    assert got.title == "T"
    assert comment.id == "c_1"
    assert comments.has_more is False
    assert [a.id for a in attachments] == ["att_1"]
    assert download.url == "https://s/dl"


def test_async_attachment_upload_walks_create_store_and_confirm():
    seen: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request.url.path)
        if request.url.host == "storage.test":
            return httpx.Response(200)
        if request.url.path.endswith("/confirm"):
            return json_response(200, {"attachment": ATTACHMENT})
        return json_response(
            201,
            {
                "attachment": {**ATTACHMENT, "status": "pending"},
                "upload": {"url": "https://storage.test/att_1", "method": "PUT", "headers": {}, "expiresAt": 1},
            },
        )

    async def main():
        client = _async_client(handler)
        attachment = await client.tasks.attachments.create(
            "task_1", CreateAttachment(filename="a.pdf", content_type="application/pdf", size_bytes=6)
        )
        uploaded = await client.tasks.attachments.upload(
            "task_1", b"hello!", filename="a.pdf", content_type="application/pdf"
        )
        confirmed = await client.tasks.attachments.confirm("task_1", "att_1")
        await client.aclose()
        return attachment, uploaded, confirmed

    created, uploaded, confirmed = asyncio.run(main())

    assert created.upload.url == "https://storage.test/att_1"
    assert uploaded.status == "ready"
    assert confirmed.id == "att_1"
    assert "/att_1" in seen  # the bytes really went to object storage


def test_async_attachment_upload_reports_a_storage_rejection():
    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.host == "storage.test":
            return httpx.Response(403)
        return json_response(
            201,
            {
                "attachment": {**ATTACHMENT, "status": "pending"},
                "upload": {"url": "https://storage.test/att_1", "method": "PUT", "headers": {}, "expiresAt": 1},
            },
        )

    async def main():
        client = _async_client(handler)
        try:
            await client.tasks.attachments.upload(
                "task_1", b"x", filename="a.pdf", content_type="application/pdf"
            )
        finally:
            await client.aclose()

    with pytest.raises(FivexerApiError) as excinfo:
        asyncio.run(main())

    assert excinfo.value.code == "upload_failed"


def test_async_worker_management_covers_detail_patch_and_availability():
    recorder = Recorder(
        {
            ("POST", "/workers"): {"id": "agent_1"},
            ("GET", "/workers"): {"workers": ["agent_1"], "count": 1},
            ("GET", "/workers/agent_1"): {"id": "agent_1", "tags": ["english"], "available": True, "queueDepth": 0},
            ("PATCH", "/workers/agent_1"): {"id": "agent_1"},
            ("POST", "/workers/agent_1/availability"): {
                "id": "agent_1",
                "available": False,
                "releasedTaskIds": ["task_1"],
            },
            ("GET", "/workers/agent_1/queue"): {"workerId": "agent_1", "taskIds": []},
            ("DELETE", "/workers/agent_1"): None,
        }
    )

    async def main():
        client = _async_client(recorder)
        upserted = await client.workers.upsert(UpsertWorker(id="agent_1"))
        listed = await client.workers.list()
        detail = await client.workers.get("agent_1")
        patched = await client.workers.patch("agent_1", PatchWorker(max_backlog_size=3))
        paused = await client.workers.set_availability("agent_1", False, release_backlog=True)
        queue = await client.workers.queue("agent_1")
        await client.workers.remove("agent_1")
        await client.aclose()
        return upserted, listed, detail, patched, paused, queue

    upserted, listed, detail, patched, paused, queue = asyncio.run(main())

    assert (upserted, patched) == ("agent_1", "agent_1")
    assert listed.count == 1
    assert detail.queue_depth == 0
    assert paused.released_task_ids == ["task_1"]
    assert queue.task_ids == []


def test_async_skill_catalogue_operations_round_trip():
    skill = {"id": "skl_1", "key": "refunds", "name": "Refunds", "description": None, "createdAt": "x"}
    recorder = Recorder(
        {
            ("POST", "/skills"): skill,
            ("GET", "/skills"): {"skills": [skill]},
            ("PATCH", "/skills/skl_1"): skill,
            ("DELETE", "/skills/skl_1"): None,
            ("GET", "/skills/suggest"): {"skills": []},
        }
    )

    async def main():
        client = _async_client(recorder)
        created = await client.skills.create(UpsertSkill(key="refunds", name="Refunds"))
        listed = await client.skills.list(q="ref")
        patched = await client.skills.patch("skl_1", PatchSkill(name="Refunds"))
        suggested = await client.skills.suggest(["billing"])
        await client.skills.remove("skl_1")
        await client.aclose()
        return created, listed, patched, suggested

    created, listed, patched, suggested = asyncio.run(main())

    assert created.key == "refunds"
    assert [s.id for s in listed] == ["skl_1"]
    assert patched.name == "Refunds"
    assert suggested == []


def test_async_decision_listing_returns_candidate_details():
    recorder = Recorder(
        {
            ("GET", "/decisions"): {
                "decisions": [
                    {
                        "id": "d_1",
                        "taskId": "task_1",
                        "workerId": "agent_1",
                        "matchedAt": 1,
                        "mode": "best-match",
                        "candidates": [{"workerId": "agent_1", "score": 180}],
                    }
                ]
            }
        }
    )

    async def main():
        client = _async_client(recorder)
        decisions = await client.decisions.list(ListDecisionsQuery(task_id="task_1"))
        await client.aclose()
        return decisions

    decisions = asyncio.run(main())

    assert decisions[0].mode == "best-match"
    assert decisions[0].candidates[0].detail == {"score": 180}


def test_async_workflow_definitions_and_runs_round_trip():
    recorder = Recorder(
        {
            ("GET", "/workflows"): {"workflows": [{"id": "wf_1", "name": "W"}]},
            ("GET", "/workflows/wf_1"): {"id": "wf_1", "name": "W", "version": 1, "initialStepId": "a", "steps": []},
            ("PUT", "/workflows/wf_1"): {"id": "wf_1", "name": "W", "version": 1, "initialStepId": "a", "steps": []},
            ("DELETE", "/workflows/wf_1"): None,
            ("POST", "/workflows/wf_1/runs"): RUN,
            ("GET", "/workflows/wf_1/runs"): {"runs": [RUN], "nextCursor": None},
            ("GET", "/workflow-runs"): {"runs": [RUN], "nextCursor": "c1"},
            ("GET", "/workflow-runs/run_1"): RUN,
            ("GET", "/workflow-runs/run_1/steps"): {"runId": "run_1", "status": "active", "steps": []},
            ("POST", "/workflow-runs/run_1/cancel"): {**RUN, "status": "cancelled"},
            ("POST", "/workflow-runs/run_1/steps/s1/complete"): RUN,
            ("POST", "/workflow-runs/run_1/steps/s1/fail"): {**RUN, "status": "failed"},
        }
    )

    async def main():
        client = _async_client(recorder)
        definitions = await client.workflows.list()
        definition = await client.workflows.get("wf_1")
        saved = await client.workflows.save(
            "wf_1", WorkflowDefinitionInput(name="W", steps=[WorkflowStep(id="a", name="A")])
        )
        started = await client.workflows.run("wf_1", StartRun(initiator_worker_id="agent_1"))
        definition_runs = await client.workflows.list_runs("wf_1", ListRunsQuery(status="active"))
        all_runs = await client.runs.list()
        run = await client.runs.get("run_1")
        steps = await client.runs.steps("run_1")
        cancelled = await client.runs.cancel("run_1")
        completed = await client.runs.complete_step("run_1", "s1", {"ok": True})
        failed = await client.runs.fail_step("run_1", "s1", "boom")
        await client.workflows.remove("wf_1")
        await client.aclose()
        return (definitions, definition, saved, started, definition_runs, all_runs, run,
                steps, cancelled, completed, failed)

    (
        definitions,
        definition,
        saved,
        started,
        definition_runs,
        all_runs,
        run,
        steps,
        cancelled,
        completed,
        failed,
    ) = asyncio.run(main())

    assert [d.id for d in definitions] == ["wf_1"]
    assert definition.initial_step_id == "a"
    assert saved.version == 1
    assert started.status == "active"
    assert len(definition_runs.runs) == 1
    assert all_runs.next_cursor == "c1"
    assert run.id == "run_1"
    assert steps.steps == []
    assert cancelled.status == "cancelled"
    assert completed.status == "active"
    assert failed.status == "failed"


def test_async_learning_operations_round_trip():
    recorder = Recorder(
        {
            ("GET", "/learning/status"): {"enabled": True, "shadowMode": False, "autoWeights": True, "stats": None},
            ("GET", "/learning/workers/agent_1"): {"workerId": "agent_1", "skills": []},
            ("GET", "/learning/weights/preview"): {"workers": []},
            ("POST", "/learning/weights/apply"): {"applied": {"agent_1": {"english": 118}}},
            ("POST", "/tasks/task_1/feedback"): {"ok": True},
            ("POST", "/tasks/task_1/reward"): {"ok": True},
            ("POST", "/learning/feedback"): {"results": [{"taskId": "task_1", "ok": True}]},
            ("POST", "/learning/reset"): {"ok": True},
        }
    )

    async def main():
        client = _async_client(recorder)
        status = await client.learning.status()
        worker_stats = await client.learning.worker_stats("agent_1")
        preview = await client.learning.preview_weights()
        applied = await client.learning.apply_weights(["agent_1"])
        feedback = await client.learning.feedback("task_1", {"csat": 1.0})
        reward = await client.learning.reward("task_1", 1.5)
        bulk = await client.learning.feedback_bulk([LearningFeedbackItem(task_id="task_1", reward=1)])
        reset = await client.learning.reset()
        await client.aclose()
        return status, worker_stats, preview, applied, feedback, reward, bulk, reset

    status, worker_stats, preview, applied, feedback, reward, bulk, reset = asyncio.run(main())

    assert status.stats is None
    assert worker_stats.worker_id == "agent_1"
    assert preview.workers == []
    assert applied == {"agent_1": {"english": 118}}
    assert (feedback, reward, reset) == (True, True, True)
    assert bulk[0].ok is True


def test_async_notification_operations_round_trip():
    sequence = {"id": "seq_1", "name": "S", "enabled": True, "steps": [], "filterTags": []}
    channel = {"id": "ch_1", "type": "webhook", "target": "https://h", "events": [], "disabled": False}
    recorder = Recorder(
        {
            ("GET", "/notification-sequences"): {"sequences": [sequence]},
            ("POST", "/notification-sequences"): sequence,
            ("GET", "/notification-sequences/seq_1"): sequence,
            ("PATCH", "/notification-sequences/seq_1"): {**sequence, "enabled": False},
            ("DELETE", "/notification-sequences/seq_1"): None,
            ("GET", "/notification-channels"): {"channels": [channel]},
            ("POST", "/notification-channels"): channel,
            ("GET", "/notification-channels/ch_1"): channel,
            ("PATCH", "/notification-channels/ch_1"): {**channel, "disabled": True},
            ("DELETE", "/notification-channels/ch_1"): None,
        }
    )

    async def main():
        client = _async_client(recorder)
        sequences = client.notifications.sequences
        channels = client.notifications.channels
        listed_seq = await sequences.list()
        created_seq = await sequences.create(CreateNotificationSequence(name="S", steps=[]))
        got_seq = await sequences.get("seq_1")
        updated_seq = await sequences.update("seq_1", UpdateNotificationSequence(enabled=False))
        await sequences.remove("seq_1")
        listed_ch = await channels.list()
        created_ch = await channels.create(
            CreateNotificationChannel(type="webhook", target="https://h", events=[])
        )
        got_ch = await channels.get("ch_1")
        updated_ch = await channels.update("ch_1", UpdateNotificationChannel(disabled=True))
        await channels.remove("ch_1")
        await client.aclose()
        return listed_seq, created_seq, got_seq, updated_seq, listed_ch, created_ch, got_ch, updated_ch

    listed_seq, created_seq, got_seq, updated_seq, listed_ch, created_ch, got_ch, updated_ch = asyncio.run(main())

    assert [s.id for s in listed_seq] == ["seq_1"]
    assert created_seq.name == "S"
    assert got_seq.enabled is True
    assert updated_seq.enabled is False
    assert [c.id for c in listed_ch] == ["ch_1"]
    assert created_ch.type == "webhook"
    assert got_ch.disabled is False
    assert updated_ch.disabled is True


def test_async_stats_history_and_presence_round_trip():
    recorder = Recorder(
        {
            ("GET", "/stats"): {"plan": "pro", "tasks": {}, "workers": 1, "meter": {}},
            ("GET", "/stats/timeseries"): {"from": "a", "to": "b", "bucket": "hour", "buckets": []},
            ("GET", "/stats/workers"): {"from": "a", "to": "b", "workers": []},
            ("GET", "/team/presence"): {
                "workers": [],
                "counts": {"working": 0, "onBreak": 0, "paused": 0, "total": 0},
            },
            ("GET", "/breaks/metrics"): {
                "workers": [],
                "totalBreakMs": 0,
                "breakCount": 0,
                "activeCount": 0,
            },
        }
    )

    async def main():
        client = _async_client(recorder)
        stats = await client.stats()
        timeseries = await client.history.timeseries(StatsWindowQuery(bucket="hour"))
        workers = await client.history.workers()
        presence = await client.team.presence()
        breaks = await client.breaks.metrics(from_="2026-07-24T00:00:00Z")
        await client.aclose()
        return stats, timeseries, workers, presence, breaks

    stats, timeseries, workers, presence, breaks = asyncio.run(main())

    assert stats.plan == "pro"
    assert timeseries.bucket == "hour"
    assert workers.workers == []
    assert presence.counts.total == 0
    assert breaks.active_count == 0


# ---- async worker portal --------------------------------------------------


def test_async_worker_portal_covers_login_queue_actions_and_breaks():
    recorder = Recorder(
        {
            ("POST", "/worker-auth/login"): {"token": "wt_new"},
            ("POST", "/worker-auth/logout"): None,
            ("GET", "/portal/workers/agent_1/queue"): {"workerId": "agent_1", "taskIds": ["task_1"]},
            ("GET", "/portal/tasks/task_1"): {"id": "task_1", "status": "pending", "tags": []},
            ("POST", "/portal/tasks/task_1/accept"): {"id": "task_1", "status": "accepted"},
            ("POST", "/portal/tasks/task_1/reject"): {"id": "task_1", "status": "queued"},
            ("POST", "/portal/tasks/task_1/complete"): {"id": "task_1", "status": "completed"},
            ("POST", "/portal/breaks/start"): {"workerId": "agent_1", "onBreak": True, "since": "x"},
            ("POST", "/portal/breaks/end"): {"workerId": "agent_1", "onBreak": False, "endedAt": "y"},
            ("GET", "/portal/breaks/today"): {"workerId": "agent_1", "since": "x", "breaks": [], "active": None},
            ("GET", "/portal/metrics/today"): {"workerId": "agent_1", "since": "x", "completedTasks": 3},
            ("GET", "/portal/team/presence"): {
                "workers": [],
                "counts": {"working": 0, "onBreak": 0, "paused": 0, "total": 0},
            },
        }
    )

    async def main():
        worker = _async_worker(recorder)
        queue = await worker.queue()
        detail = await worker.task_detail("task_1")
        accepted = await worker.accept("task_1")
        rejected = await worker.reject("task_1")
        completed = await worker.complete("task_1", {"ok": True})
        started = await worker.start_break("lunch")
        ended = await worker.end_break()
        today = await worker.breaks_today()
        metrics = await worker.metrics_today()
        presence = await worker.team_presence()
        session = await worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="1"))
        await worker.logout()
        forgotten = worker.session_token
        await worker.aclose()
        return (queue, detail, accepted, rejected, completed, started, ended, today,
                metrics, presence, session, forgotten)

    (
        queue,
        detail,
        accepted,
        rejected,
        completed,
        started,
        ended,
        today,
        metrics,
        presence,
        session,
        forgotten,
    ) = asyncio.run(main())

    assert queue.task_ids == ["task_1"]
    assert detail.status == "pending"
    assert (accepted.status, rejected.status, completed.status) == ("accepted", "queued", "completed")
    assert started.on_break is True
    assert ended is not None and ended.on_break is False
    assert today.active is None
    assert metrics.completed_tasks == 3
    assert presence.counts.total == 0
    assert session.token == "wt_new"
    assert forgotten is None


def test_async_ending_a_break_when_none_is_open_returns_nothing():
    async def main():
        worker = _async_worker(lambda request: httpx.Response(204))
        result = await worker.end_break()
        await worker.aclose()
        return result

    assert asyncio.run(main()) is None


def test_async_worker_refuses_to_act_without_a_worker_id():
    async def main():
        worker = AsyncFivexerWorker(
            base_url=BASE, http_client=httpx.AsyncClient(transport=httpx.MockTransport(lambda r: httpx.Response(200)))
        )
        try:
            await worker.queue()
        finally:
            await worker.aclose()

    with pytest.raises(FivexerApiError) as excinfo:
        asyncio.run(main())

    assert excinfo.value.code == "worker_id_required"


def test_async_worker_adopts_a_token_set_after_construction():
    seen: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request.headers.get("authorization", ""))
        return json_response(200, {"workerId": "agent_7", "taskIds": []})

    async def main():
        worker = AsyncFivexerWorker(
            base_url=BASE, http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler))
        )
        worker.set_token("wt_restored", "agent_7")
        result = await worker.queue()
        await worker.aclose()
        return result

    queue = asyncio.run(main())

    assert queue.worker_id == "agent_7"
    assert seen == ["Bearer wt_restored"]


def test_async_worker_requires_a_base_url():
    with pytest.raises(ValueError):
        AsyncFivexerWorker(base_url="")


def test_async_worker_retries_a_transient_read_failure():
    calls = {"n": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        calls["n"] += 1
        if calls["n"] == 1:
            return json_response(503, {"error": {"code": "internal_error", "message": "boom"}})
        return json_response(200, {"workerId": "agent_1", "taskIds": []})

    async def main():
        worker = AsyncFivexerWorker(
            base_url=BASE,
            token="wt_1",
            worker_id="agent_1",
            max_retries=1,
            http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
        )
        result = await worker.queue()
        await worker.aclose()
        return result

    assert asyncio.run(main()).worker_id == "agent_1"
    assert calls["n"] == 2


def test_async_worker_surfaces_a_structured_error_from_an_unparseable_body():
    async def main():
        worker = _async_worker(lambda request: httpx.Response(500, content=b"<html>"))
        try:
            await worker.queue()
        finally:
            await worker.aclose()

    with pytest.raises(FivexerApiError) as excinfo:
        asyncio.run(main())

    assert excinfo.value.code == "unknown_error"


def test_async_worker_exposes_its_identity_and_closes_cleanly():
    async def main():
        worker = AsyncFivexerWorker(base_url=f"{BASE}/", token="wt_1", worker_id="agent_1")
        async with worker:
            identity = (worker.base_url, worker.worker_id, worker.session_token)
        return identity

    assert asyncio.run(main()) == (BASE, "agent_1", "wt_1")


def test_async_client_closes_the_transport_it_created():
    async def main():
        async with AsyncFivexer(base_url=BASE, api_key="sk_test") as client:
            return client.base_url

    assert asyncio.run(main()) == BASE


def test_async_worker_waits_the_retry_after_interval_before_retrying():
    # A 1ms Retry-After exercises the real sleep path without slowing the suite.
    calls = {"n": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        calls["n"] += 1
        if calls["n"] == 1:
            return httpx.Response(503, json={"error": {"code": "busy", "message": "b"}},
                                  headers={"retry-after": "0.001"})
        return json_response(200, {"workerId": "agent_1", "taskIds": []})

    async def main():
        worker = AsyncFivexerWorker(
            base_url=BASE, token="wt_1", worker_id="agent_1", max_retries=1,
            http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
        )
        result = await worker.queue()
        await worker.aclose()
        return result

    assert asyncio.run(main()).worker_id == "agent_1"
    assert calls["n"] == 2
