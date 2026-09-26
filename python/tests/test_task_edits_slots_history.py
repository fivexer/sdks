"""Editing a live task, timeslots and advance booking, and the finished-task archive.

`tasks.update` is the part worth reading slowly: a retag can take work off the worker holding
it, and the SDK has to surface `requeued` or the edit looks like a silent no-op. Patch semantics
mirror the rest of the SDK — `None` is "leave alone", `CLEAR` is an explicit null.

A slot is a different clock from `schedule`: `schedule` says when a task may be handed out, a
slot says when the work is performed. Ahead of it a worker is booked for it, and those bookings
are their own read because no task listing answers "who is on what, when".
"""

from __future__ import annotations

import asyncio

import pytest

from fivexer import (
    CLEAR,
    CreateTask,
    FivexerApiError,
    TaskHistoryQuery,
    TaskReferenceInput,
    TimeSlot,
    UpdateTaskInput,
)
from tests.conftest import empty_response, json_response, query_pairs, read_body

BOOKING = {
    "taskId": "task_1",
    "workerId": "w-1",
    "startAt": 1_760_000_000_000,
    "endAt": 1_760_003_600_000,
    "bookedAt": 1_759_900_000_000,
    "source": "manual",
    "warnings": [{"from": 1_760_000_000_000, "to": 1_760_001_800_000, "reason": "outside_shift"}],
}

ARCHIVED = {
    "id": "task_9",
    "tags": ["plumbing"],
    "priority": 40,
    "status": "completed",
    "workerId": "w-1",
    "createdAt": 1_759_000_000_000,
    "matchedAt": 1_759_000_060_000,
    "terminalAt": 1_759_003_600_000,
    "meta": {"ticket": "T-1"},
    "title": "Fix the tap",
    "result": {"ok": True},
    "data": {"hasContext": True, "referenceCount": 1, "attachmentCount": 0, "commentCount": 2},
    "archived": True,
}


# ─────────────────────────────── tasks.update ───────────────────────────────


def test_update_patches_only_the_fields_that_were_set(server, client):
    server.set_response(
        json_response(
            200,
            {"id": "task_1", "status": "pending", "tags": ["billing"], "priority": 70, "requeued": False},
        )
    )

    result = client.tasks.update("task_1", UpdateTaskInput(priority=70, title="Refund"))

    assert server.last.method == "PATCH"
    assert server.last.url.path == "/v1/tasks/task_1"
    # Omitted fields are absent, not null — null would clear them.
    assert read_body(server.last) == {"priority": 70, "title": "Refund"}
    assert result.status == "pending"
    assert result.requeued is False
    assert result.previous_worker_id is None


def test_update_sends_an_explicit_null_only_for_fields_marked_clear(server, client):
    server.set_response(json_response(200, {"id": "task_1", "status": "queued", "tags": [], "requeued": False}))

    client.tasks.update(
        "task_1",
        UpdateTaskInput(description=CLEAR, context=CLEAR, references=CLEAR, meta=CLEAR, title=CLEAR),
    )

    assert read_body(server.last) == {
        "title": None,
        "description": None,
        "context": None,
        "references": None,
        "meta": None,
    }


def test_update_serialises_tags_context_and_references(server, client):
    server.set_response(json_response(200, {"id": "task_1", "status": "queued", "tags": ["de"], "requeued": True}))

    client.tasks.update(
        "task_1",
        UpdateTaskInput(
            tags=["de"],
            context={"orderId": 7},
            meta={"src": "crm"},
            references=[TaskReferenceInput(url="https://crm.example/7", content_type="text/html")],
        ),
    )

    assert read_body(server.last) == {
        "tags": ["de"],
        "context": {"orderId": 7},
        "meta": {"src": "crm"},
        "references": [{"url": "https://crm.example/7", "contentType": "text/html"}],
    }


def test_a_retag_that_no_longer_reaches_the_holder_reports_the_requeue(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_1",
                "status": "queued",
                "tags": ["german"],
                "priority": None,
                "requeued": True,
                "previousWorkerId": "w-1",
            },
        )
    )

    result = client.tasks.update("task_1", UpdateTaskInput(tags=["german"]))

    # Without these two fields the edit would read as a no-op while the worker lost the task.
    assert result.requeued is True
    assert result.previous_worker_id == "w-1"
    assert result.status == "queued"
    assert result.tags == ["german"]
    assert result.priority is None


def test_update_rejects_a_raw_dict_with_a_clear_error(client):
    with pytest.raises(TypeError, match="expected UpdateTaskInput, got dict"):
        client.tasks.update("task_1", {"priority": 1})  # type: ignore[arg-type]


# ─────────────────────────────── slots on tasks ───────────────────────────────


def test_create_sends_the_slot_with_only_the_options_that_were_set(server, client):
    server.set_response(json_response(201, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["visit"], slot=TimeSlot(start_at=1_760_000_000_000, duration_ms=3_600_000)))

    assert read_body(server.last)["slot"] == {"startAt": 1_760_000_000_000, "durationMs": 3_600_000}


def test_create_sends_the_slot_booking_horizon_and_unbooked_rule(server, client):
    server.set_response(json_response(201, {"id": "task_1", "status": "queued"}))

    client.tasks.create(
        CreateTask(
            tags=["visit"],
            slot=TimeSlot(start_at=1, duration_ms=60_000, book_ahead_ms=86_400_000, on_unbooked="park"),
        )
    )

    assert read_body(server.last)["slot"] == {
        "startAt": 1,
        "durationMs": 60_000,
        "bookAheadMs": 86_400_000,
        "onUnbooked": "park",
    }


def test_a_task_without_a_slot_sends_none(server, client):
    server.set_response(json_response(201, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["visit"]))

    assert "slot" not in read_body(server.last)


def test_a_single_task_read_carries_its_slot_and_booking(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_1",
                "tags": ["visit"],
                "status": "scheduled",
                "slot": {"startAt": 1_760_000_000_000, "durationMs": 3_600_000, "onUnbooked": "queue"},
                "booking": BOOKING,
            },
        )
    )

    task = client.tasks.get("task_1")

    assert task.slot is not None
    assert task.slot.start_at == 1_760_000_000_000
    assert task.slot.duration_ms == 3_600_000
    assert task.slot.book_ahead_ms is None
    assert task.slot.on_unbooked == "queue"
    assert task.booking is not None
    assert task.booking.worker_id == "w-1"
    assert task.booking.end_at == 1_760_003_600_000


def test_a_slotted_task_nobody_is_booked_for_reads_booking_as_none(server, client):
    server.set_response(
        json_response(
            200,
            {"id": "task_1", "tags": [], "status": "scheduled", "slot": {"startAt": 1, "durationMs": 60_000}},
        )
    )

    task = client.tasks.get("task_1")

    # Unbooked is a real answer on a slotted task — the one a planner acts on.
    assert task.slot is not None
    assert task.booking is None


# ─────────────────────────────── bookings ───────────────────────────────


def test_bookings_reads_a_window_and_parses_the_soft_conflicts(server, client):
    server.set_response(json_response(200, {"bookings": [BOOKING], "count": 1}))

    bookings = client.tasks.bookings(1_760_000_000_000, 1_760_604_800_000, worker_id="w-1")

    assert server.last.method == "GET"
    assert server.last.url.path == "/v1/tasks/bookings"
    assert query_pairs(server.last) == {"from": "1760000000000", "to": "1760604800000", "workerId": "w-1"}
    booking = bookings[0]
    assert booking.task_id == "task_1"
    assert booking.source == "manual"
    assert booking.booked_at == 1_759_900_000_000
    assert booking.warnings is not None
    assert booking.warnings[0].from_ == 1_760_000_000_000
    assert booking.warnings[0].to == 1_760_001_800_000
    assert booking.warnings[0].reason == "outside_shift"


def test_bookings_omits_the_worker_filter_when_not_given(server, client):
    server.set_response(json_response(200, {"bookings": [], "count": 0}))

    assert client.tasks.bookings(1, 2) == []
    assert query_pairs(server.last) == {"from": "1", "to": "2"}


def test_booking_candidates_ranks_workers_with_what_blocks_them(server, client):
    server.set_response(
        json_response(
            200,
            {
                "candidates": [
                    {
                        "workerId": "w-1",
                        "score": 0.9,
                        "effectivePriority": 91.5,
                        "bookable": True,
                        "reasons": [{"type": "skill", "ok": True}],
                    },
                    {
                        "workerId": "w-2",
                        "score": 0.8,
                        "effectivePriority": 80,
                        "bookable": False,
                        "reasons": [],
                        "clashingTaskId": "task_7",
                        "blocked": [{"from": 1, "to": 2, "reason": "time_off"}],
                        "warnings": [{"from": 3, "to": 4, "reason": "calendar_busy"}],
                    },
                ],
                "count": 2,
            },
        )
    )

    candidates = client.tasks.booking.candidates("task/1")

    assert server.last.url.raw_path.decode() == "/v1/tasks/task%2F1/booking/candidates"
    first, second = candidates
    assert first.bookable is True
    assert first.effective_priority == 91.5
    assert first.reasons == [{"type": "skill", "ok": True}]
    assert first.blocked is None
    assert first.warnings is None
    assert second.clashing_task_id == "task_7"
    assert second.blocked is not None and second.blocked[0].reason == "time_off"
    assert second.warnings is not None and second.warnings[0].reason == "calendar_busy"


def test_booking_set_books_a_worker_and_sends_force_only_when_asked(server, client):
    server.set_response(json_response(200, {"id": "task_1", "booking": {**BOOKING, "warnings": None}}))

    booking = client.tasks.booking.set("task_1", "w-1")

    assert server.last.method == "POST"
    assert server.last.url.path == "/v1/tasks/task_1/booking"
    assert read_body(server.last) == {"workerId": "w-1"}
    assert booking.worker_id == "w-1"
    assert booking.warnings is None

    client.tasks.booking.set("task_1", "w-1", force=True)

    assert read_body(server.last) == {"workerId": "w-1", "force": True}


def test_booking_set_surfaces_a_clash_as_a_409(server, client):
    server.set_response(json_response(409, {"error": {"code": "booking_clash", "message": "overlaps task_7"}}))

    with pytest.raises(FivexerApiError) as raised:
        client.tasks.booking.set("task_1", "w-2")

    assert raised.value.status_code == 409
    assert raised.value.code == "booking_clash"


def test_booking_release_unbooks_the_slot(server, client):
    server.set_response(empty_response(204))

    assert client.tasks.booking.release("task_1") is None
    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/tasks/task_1/booking"


# ─────────────────────────────── history ───────────────────────────────


def test_history_joins_several_statuses_into_one_parameter(server, client):
    server.set_response(json_response(200, {"tasks": [ARCHIVED], "nextCursor": "c2", "hasMore": True}))

    page = client.tasks.history(
        TaskHistoryQuery(
            status=["completed", "failed"],
            worker_id="w-1",
            tag="plumbing",
            from_="2026-09-01T00:00:00.000Z",
            to="2026-09-08T00:00:00.000Z",
            q="tap",
            cursor="c1",
            limit=25,
        )
    )

    assert server.last.url.path == "/v1/tasks/history"
    assert query_pairs(server.last) == {
        "status": "completed,failed",
        "workerId": "w-1",
        "tag": "plumbing",
        "from": "2026-09-01T00:00:00.000Z",
        "to": "2026-09-08T00:00:00.000Z",
        "q": "tap",
        "cursor": "c1",
        "limit": "25",
    }
    assert page.next_cursor == "c2"
    assert page.has_more is True
    task = page.tasks[0]
    assert task.status == "completed"
    assert task.matched_at == 1_759_000_060_000
    assert task.terminal_at == 1_759_003_600_000
    assert task.result == {"ok": True}
    assert task.data is not None and task.data.comment_count == 2
    assert task.archived is True


def test_history_takes_a_single_status_as_is_and_omits_an_empty_list(server, client):
    server.set_response(json_response(200, {"tasks": [], "nextCursor": None, "hasMore": False}))

    client.tasks.history(TaskHistoryQuery(status="cancelled"))
    assert query_pairs(server.last) == {"status": "cancelled"}

    client.tasks.history(TaskHistoryQuery(status=[]))
    assert query_pairs(server.last) == {}

    page = client.tasks.history()
    assert query_pairs(server.last) == {}
    assert page.tasks == []
    assert page.next_cursor is None


def test_an_archived_task_that_was_never_matched_reads_the_gaps_as_none(server, client):
    server.set_response(
        json_response(
            200,
            {
                "tasks": [
                    {
                        "id": "task_2",
                        "tags": [],
                        "priority": None,
                        "status": "cancelled",
                        "workerId": None,
                        "createdAt": None,
                        "matchedAt": None,
                        "terminalAt": 5,
                        "meta": None,
                        "title": None,
                        "result": None,
                        "data": None,
                        "archived": True,
                    }
                ],
                "nextCursor": None,
                "hasMore": False,
            },
        )
    )

    task = client.tasks.history().tasks[0]

    assert task.worker_id is None
    assert task.matched_at is None
    assert task.created_at is None
    assert task.data is None


# ─────────────────────────────── async ───────────────────────────────


def test_the_async_client_edits_books_and_reads_history_the_same_way(server, async_client):
    async def scenario() -> None:
        server.set_response(json_response(200, {"id": "task_1", "status": "queued", "tags": ["de"], "requeued": True}))
        result = await async_client.tasks.update("task_1", UpdateTaskInput(tags=["de"]))
        assert result.requeued is True
        assert read_body(server.last) == {"tags": ["de"]}

        server.set_response(json_response(200, {"tasks": [ARCHIVED], "nextCursor": None, "hasMore": False}))
        page = await async_client.tasks.history(TaskHistoryQuery(status=["completed", "expired"]))
        assert query_pairs(server.last) == {"status": "completed,expired"}
        assert page.tasks[0].id == "task_9"

        server.set_response(json_response(200, {"bookings": [BOOKING], "count": 1}))
        bookings = await async_client.tasks.bookings(1, 2)
        assert bookings[0].task_id == "task_1"

        server.set_response(
            json_response(
                200,
                {
                    "candidates": [{"workerId": "w-1", "score": 1, "effectivePriority": 1, "bookable": True}],
                    "count": 1,
                },
            )
        )
        candidates = await async_client.tasks.booking.candidates("task_1")
        assert candidates[0].worker_id == "w-1"
        assert candidates[0].reasons == []

        server.set_response(json_response(200, {"id": "task_1", "booking": BOOKING}))
        booking = await async_client.tasks.booking.set("task_1", "w-1", force=True)
        assert read_body(server.last) == {"workerId": "w-1", "force": True}
        assert booking.source == "manual"

        server.set_response(empty_response(204))
        await async_client.tasks.booking.release("task_1")
        assert server.last.method == "DELETE"
        assert server.last.url.path == "/v1/tasks/task_1/booking"

    asyncio.run(scenario())
