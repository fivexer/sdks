"""The working-time record beyond reading it: corrections, added shifts and the trail they leave;
the team report; what offboarding would hand back; connector links; and expiring skills.

A correction keeps what the record said before, carries a mandatory reason, and is refused in a
signed-off period — a correction, not an edit. Nullable fields follow the SDK-wide rule: `None`
leaves a field alone, `CLEAR` sends an explicit null (reopening a shift closed by mistake).
"""

from __future__ import annotations

import asyncio

import pytest

from fivexer import (
    CLEAR,
    CorrectTimeEntryInput,
    CreateTimeEntryInput,
    FivexerApiError,
    LinkWorkerInput,
    TeamTimeQuery,
    TimeEntryBreakInput,
)
from tests.conftest import empty_response, json_response, query_pairs, read_body

CORRECTION = {
    "id": "corr_1",
    "entryType": "shift",
    "entryId": "shift_1",
    "workerId": "w-1",
    "before": {"endedAt": None},
    "after": {"endedAt": "2026-09-20T17:00:00.000Z"},
    "note": "Forgot to clock out",
    "by": "ops@example.com",
    "at": "2026-09-21T08:00:00.000Z",
}

ENTRY = {
    "id": "shift_1",
    "type": "shift",
    "startedAt": "2026-09-20T09:00:00.000Z",
    "endedAt": "2026-09-20T17:00:00.000Z",
    "corrected": True,
    "source": "operator",
    "endReason": "manual",
    "reason": None,
    "description": "Stocktake",
    "taskId": "task_1",
}

LINK = {"connector": "hubspot", "vendorUserId": "owner_42", "workerId": "w-1", "createdAt": "2026-09-01T00:00:00Z"}


# ─────────────────────────────── corrections ───────────────────────────────


def test_correcting_a_shift_sends_the_reason_and_only_what_changed(server, client):
    server.set_response(json_response(200, {"entry": ENTRY, "correction": CORRECTION}))

    result = client.workers.correct_time_entry(
        "w-1", "shift_1", CorrectTimeEntryInput(note="Forgot to clock out", ended_at="2026-09-20T17:00:00.000Z")
    )

    assert server.last.method == "PATCH"
    assert server.last.url.path == "/v1/workers/w-1/time-entries/shift_1"
    assert read_body(server.last) == {"note": "Forgot to clock out", "endedAt": "2026-09-20T17:00:00.000Z"}
    assert result.entry.ended_at == "2026-09-20T17:00:00.000Z"
    assert result.entry.corrected is True
    assert result.entry.description == "Stocktake"
    assert result.entry.task_id == "task_1"
    assert result.correction.before == {"endedAt": None}
    assert result.correction.by == "ops@example.com"
    # Breaks and per-record trail are present only when a shift was saved with its breaks.
    assert result.breaks is None
    assert result.corrections is None


def test_clear_reopens_a_shift_and_unsets_its_note_and_task(server, client):
    server.set_response(json_response(200, {"entry": {**ENTRY, "endedAt": None}, "correction": CORRECTION}))

    result = client.workers.correct_time_entry(
        "w-1",
        "shift_1",
        CorrectTimeEntryInput(note="Still working", ended_at=CLEAR, description=CLEAR, task_id=CLEAR, type="shift"),
    )

    assert read_body(server.last) == {
        "note": "Still working",
        "endedAt": None,
        "description": None,
        "taskId": None,
        "type": "shift",
    }
    assert result.entry.ended_at is None


def test_a_shift_saved_with_its_breaks_returns_every_break_and_trail_entry(server, client):
    brk = {"id": "brk_1", "type": "break", "startedAt": "2026-09-20T12:00:00.000Z", "endedAt": None}
    server.set_response(
        json_response(
            200,
            {
                "entry": ENTRY,
                "correction": CORRECTION,
                "breaks": [brk],
                "corrections": [CORRECTION, {**CORRECTION, "id": "corr_2", "entryType": "break", "before": None}],
            },
        )
    )

    result = client.workers.correct_time_entry(
        "w-1",
        "shift_1",
        CorrectTimeEntryInput(
            note="Lunch was longer",
            started_at="2026-09-20T08:30:00.000Z",
            breaks=[
                TimeEntryBreakInput(id="brk_1", started_at="2026-09-20T12:00:00.000Z", ended_at=None),
                TimeEntryBreakInput(started_at="2026-09-20T15:00:00.000Z", ended_at="2026-09-20T15:15:00.000Z"),
            ],
        ),
    )

    assert read_body(server.last) == {
        "note": "Lunch was longer",
        "startedAt": "2026-09-20T08:30:00.000Z",
        "breaks": [
            # An open break sends an explicit null end; a new break carries no id.
            {"id": "brk_1", "startedAt": "2026-09-20T12:00:00.000Z", "endedAt": None},
            {"startedAt": "2026-09-20T15:00:00.000Z", "endedAt": "2026-09-20T15:15:00.000Z"},
        ],
    }
    assert result.breaks is not None and result.breaks[0].type == "break"
    assert result.breaks[0].ended_at is None
    assert result.corrections is not None and result.corrections[1].before is None


def test_a_correction_inside_a_closed_period_is_refused(server, client):
    server.set_response(json_response(409, {"error": {"code": "period_closed", "message": "signed off"}}))

    with pytest.raises(FivexerApiError) as raised:
        client.workers.correct_time_entry("w-1", "shift_1", CorrectTimeEntryInput(note="late"))

    assert raised.value.code == "period_closed"


def test_creating_a_shift_sends_both_ends_the_reason_and_its_breaks(server, client):
    server.set_response(json_response(201, {"entry": ENTRY, "correction": {**CORRECTION, "before": None}}))

    result = client.workers.create_time_entry(
        "w/1",
        CreateTimeEntryInput(
            started_at="2026-09-20T09:00:00.000Z",
            ended_at="2026-09-20T17:00:00.000Z",
            note="Worked, never logged",
            breaks=[TimeEntryBreakInput(started_at="2026-09-20T12:00:00.000Z", ended_at="2026-09-20T12:30:00.000Z")],
            description="Stocktake",
            task_id="task_1",
        ),
    )

    assert server.last.method == "POST"
    assert server.last.url.raw_path.decode() == "/v1/workers/w%2F1/time-entries"
    assert read_body(server.last) == {
        "startedAt": "2026-09-20T09:00:00.000Z",
        "endedAt": "2026-09-20T17:00:00.000Z",
        "note": "Worked, never logged",
        "breaks": [{"startedAt": "2026-09-20T12:00:00.000Z", "endedAt": "2026-09-20T12:30:00.000Z"}],
        "description": "Stocktake",
        "taskId": "task_1",
    }
    # A created row has nothing before it.
    assert result.correction.before is None


def test_creating_a_bare_shift_omits_the_optional_fields(server, client):
    server.set_response(json_response(201, {"entry": ENTRY, "correction": CORRECTION}))

    client.workers.create_time_entry("w-1", CreateTimeEntryInput(started_at="a", ended_at="b", note="n"))

    assert read_body(server.last) == {"startedAt": "a", "endedAt": "b", "note": "n"}


def test_correction_inputs_reject_a_raw_dict(client):
    with pytest.raises(TypeError, match="expected CorrectTimeEntryInput"):
        client.workers.correct_time_entry("w-1", "e", {"note": "x"})  # type: ignore[arg-type]
    with pytest.raises(TypeError, match="expected CreateTimeEntryInput"):
        client.workers.create_time_entry("w-1", {"note": "x"})  # type: ignore[arg-type]


def test_the_trail_lists_every_change_to_a_persons_records(server, client):
    server.set_response(json_response(200, {"workerId": "w-1", "corrections": [CORRECTION]}))

    trail = client.workers.time_corrections("w-1")

    assert server.last.url.path == "/v1/workers/w-1/time-corrections"
    assert trail.worker_id == "w-1"
    assert trail.corrections[0].entry_type == "shift"
    assert trail.corrections[0].entry_id == "shift_1"
    assert trail.corrections[0].after == {"endedAt": "2026-09-20T17:00:00.000Z"}
    assert trail.corrections[0].note == "Forgot to clock out"
    assert trail.corrections[0].at == "2026-09-21T08:00:00.000Z"


def test_the_time_log_carries_each_shifts_note_and_task(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "entries": [
                    {**ENTRY, "durationMs": 28_800_000},
                    {"id": "b", "type": "break", "startedAt": "x", "endedAt": "y", "durationMs": 1},
                ],
            },
        )
    )

    log = client.workers.time_entries("w-1")

    assert log.entries[0].description == "Stocktake"
    assert log.entries[0].task_id == "task_1"
    assert log.entries[1].description is None
    assert log.entries[1].task_id is None


# ─────────────────────────────── offboarding ───────────────────────────────


def test_offboarding_previews_what_removal_hands_back(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "lastDay": "2026-09-30",
                "tasks": {"pending": 2, "accepted": 1, "booked": 3},
                "rosters": [{"rosterId": "r-1", "name": "October", "published": True, "shifts": 4}],
                "coverRequests": 1,
                "coverOffers": 2,
                "swaps": 1,
                "pendingTimeOff": 1,
                "leavePolicies": 2,
                "teams": 3,
            },
        )
    )

    summary = client.workers.offboarding("w-1", last_day="2026-09-30")

    assert server.last.method == "GET"
    assert server.last.url.path == "/v1/workers/w-1/offboarding"
    assert query_pairs(server.last) == {"lastDay": "2026-09-30"}
    assert summary.last_day == "2026-09-30"
    assert (summary.tasks.pending, summary.tasks.accepted, summary.tasks.booked) == (2, 1, 3)
    assert summary.rosters[0].roster_id == "r-1"
    assert summary.rosters[0].published is True
    assert summary.rosters[0].shifts == 4
    assert summary.cover_requests == 1
    assert summary.cover_offers == 2
    assert summary.swaps == 1
    assert summary.pending_time_off == 1
    assert summary.leave_policies == 2
    assert summary.teams == 3


def test_offboarding_without_a_last_day_lets_the_server_pick_today(server, client):
    server.set_response(json_response(200, {"workerId": "w-1", "lastDay": "2026-09-26"}))

    summary = client.workers.offboarding("w-1")

    assert query_pairs(server.last) == {}
    assert summary.tasks.pending == 0
    assert summary.rosters == []


# ─────────────────────────────── connector links ───────────────────────────────


def test_links_lists_and_filters_by_connector(server, client):
    server.set_response(json_response(200, {"links": [LINK]}))

    links = client.workers.links(connector="hubspot")

    assert server.last.url.path == "/v1/workers/links"
    assert query_pairs(server.last) == {"connector": "hubspot"}
    assert links[0].vendor_user_id == "owner_42"
    assert links[0].created_at == "2026-09-01T00:00:00Z"

    client.workers.links()
    assert query_pairs(server.last) == {}


def test_link_puts_the_vendor_identity_and_the_tags_to_add(server, client):
    server.set_response(json_response(200, LINK))

    link = client.workers.link("w-1", "hubspot", LinkWorkerInput(vendor_user_id="owner_42", tags=["sales"]))

    assert server.last.method == "PUT"
    assert server.last.url.path == "/v1/workers/w-1/links/hubspot"
    assert read_body(server.last) == {"vendorUserId": "owner_42", "tags": ["sales"]}
    assert link.connector == "hubspot"
    assert link.worker_id == "w-1"

    client.workers.link("w-1", "hubspot", LinkWorkerInput(vendor_user_id="owner_42"))
    assert read_body(server.last) == {"vendorUserId": "owner_42"}


def test_a_link_to_somebody_else_is_a_conflict(server, client):
    server.set_response(json_response(409, {"error": {"code": "worker_link_conflict", "message": "linked"}}))

    with pytest.raises(FivexerApiError) as raised:
        client.workers.link("w-1", "jira", LinkWorkerInput(vendor_user_id="acc_1"))

    assert raised.value.code == "worker_link_conflict"


def test_unlink_deletes_the_pair(server, client):
    server.set_response(empty_response(204))

    assert client.workers.unlink("w-1", "jira/cloud") is None
    assert server.last.method == "DELETE"
    assert server.last.url.raw_path.decode() == "/v1/workers/w-1/links/jira%2Fcloud"


# ─────────────────────────────── expiring skills ───────────────────────────────


def test_expiring_skills_reads_the_lapsed_first(server, client):
    server.set_response(
        json_response(
            200,
            {
                "asOf": "2026-09-26",
                "skills": [
                    {
                        "workerId": "w-1",
                        "label": "Mari",
                        "skillId": "sk_1",
                        "key": "forklift",
                        "name": "Forklift licence",
                        "validUntil": "2026-09-20",
                        "expired": True,
                    },
                    {
                        "workerId": "w-2",
                        "label": "w-2",
                        "skillId": "sk_1",
                        "key": "forklift",
                        "name": "Forklift licence",
                        "validUntil": "2026-10-10",
                        "expired": False,
                    },
                ],
            },
        )
    )

    result = client.skills.expiring(within_days=14, as_of="2026-09-26")

    assert server.last.url.path == "/v1/skills/expiring"
    assert query_pairs(server.last) == {"withinDays": "14", "asOf": "2026-09-26"}
    assert result.as_of == "2026-09-26"
    assert [s.expired for s in result.skills] == [True, False]
    assert result.skills[0].valid_until == "2026-09-20"
    assert result.skills[0].skill_id == "sk_1"
    assert result.skills[0].key == "forklift"
    assert result.skills[0].name == "Forklift licence"
    assert result.skills[0].label == "Mari"

    client.skills.expiring()
    assert query_pairs(server.last) == {}


# ─────────────────────────────── team time ───────────────────────────────

TEAM_TIME = {
    "from": "2026-09-01T00:00:00.000Z",
    "to": "2026-09-08T00:00:00.000Z",
    "workers": [
        {
            "workerId": "w-1",
            "label": "Mari",
            "shiftCount": 5,
            "onShiftMs": 144_000_000,
            "breakCount": 5,
            "breakMs": 9_000_000,
            "workingMs": 135_000_000,
            "openShift": True,
            "openBreak": False,
            "completed": 30,
            "offered": 40,
            "accepted": 35,
            "rejected": 3,
            "expired": 2,
            "failed": 1,
            "released": 1,
            "entries": [{**ENTRY, "durationMs": 28_800_000}],
        }
    ],
    "days": [{"day": "2026-09-01", "onShiftMs": 28_800_000, "breakMs": 1_800_000, "workingMs": 27_000_000}],
    "totals": {
        "workerCount": 1,
        "shiftCount": 5,
        "onShiftMs": 144_000_000,
        "breakCount": 5,
        "breakMs": 9_000_000,
        "workingMs": 135_000_000,
        "completed": 30,
        "offered": 40,
        "accepted": 35,
        "rejected": 3,
        "expired": 2,
        "failed": 1,
    },
    "truncated": False,
}


def test_team_time_reports_worked_time_and_outcomes_per_worker_and_day(server, client):
    server.set_response(json_response(200, TEAM_TIME))

    report = client.team.time(
        TeamTimeQuery(
            from_="2026-09-01T00:00:00.000Z",
            to="2026-09-08T00:00:00.000Z",
            team_id="team_1",
            worker_id="w-1",
            entries=True,
        )
    )

    assert server.last.url.path == "/v1/team/time"
    # Lowercase: the server parses the string form, and Python's repr would send "True".
    assert query_pairs(server.last) == {
        "from": "2026-09-01T00:00:00.000Z",
        "to": "2026-09-08T00:00:00.000Z",
        "teamId": "team_1",
        "workerId": "w-1",
        "entries": "true",
    }
    assert report.from_ == "2026-09-01T00:00:00.000Z"
    row = report.workers[0]
    # Breaks happen on shift, so worked time is the difference.
    assert row.working_ms == row.on_shift_ms - row.break_ms
    assert row.open_shift is True and row.open_break is False
    assert (row.completed, row.offered, row.accepted, row.rejected) == (30, 40, 35, 3)
    assert (row.expired, row.failed, row.released) == (2, 1, 1)
    assert row.shift_count == 5 and row.break_count == 5
    assert row.label == "Mari"
    assert row.entries is not None and row.entries[0].task_id == "task_1"
    assert report.days is not None and report.days[0].working_ms == 27_000_000
    assert report.days[0].day == "2026-09-01"
    assert report.days[0].on_shift_ms == 28_800_000
    assert report.days[0].break_ms == 1_800_000
    assert report.totals.worker_count == 1
    assert report.totals.working_ms == 135_000_000
    assert report.totals.failed == 1
    assert report.truncated is False


def test_team_time_without_entries_has_no_day_series(server, client):
    server.set_response(json_response(200, {"from": "a", "to": "b", "workers": [{"workerId": "w-1"}], "days": None}))

    report = client.team.time()

    assert query_pairs(server.last) == {}
    assert report.days is None
    assert report.workers[0].entries is None
    assert report.totals.worker_count == 0

    client.team.time(TeamTimeQuery(entries=False))
    assert query_pairs(server.last) == {"entries": "false"}


# ─────────────────────────────── worker metrics today ───────────────────────────────


def test_metrics_today_carries_the_open_shift_start(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "since": "2026-09-26T00:00:00.000Z",
                "currentShiftStartedAt": "2026-09-26T07:00:00Z",
            },
        )
    )

    today = worker.metrics_today()

    assert today.current_shift_started_at == "2026-09-26T07:00:00Z"


def test_metrics_today_off_shift_reads_the_open_shift_start_as_none(server, worker):
    server.set_response(json_response(200, {"workerId": "agent_1", "since": "x", "currentShiftStartedAt": None}))

    assert worker.metrics_today().current_shift_started_at is None


# ─────────────────────────────── async ───────────────────────────────


def test_the_async_client_covers_the_same_working_time_surface(server, async_client):
    async def scenario() -> None:
        server.set_response(json_response(200, {"entry": ENTRY, "correction": CORRECTION}))
        corrected = await async_client.workers.correct_time_entry("w-1", "shift_1", CorrectTimeEntryInput(note="n"))
        assert corrected.entry.id == "shift_1"
        assert read_body(server.last) == {"note": "n"}

        created = await async_client.workers.create_time_entry(
            "w-1", CreateTimeEntryInput(started_at="a", ended_at="b", note="n")
        )
        assert server.last.method == "POST"
        assert created.correction.id == "corr_1"

        server.set_response(json_response(200, {"workerId": "w-1", "corrections": [CORRECTION]}))
        trail = await async_client.workers.time_corrections("w-1")
        assert trail.corrections[0].id == "corr_1"

        server.set_response(json_response(200, {"workerId": "w-1", "lastDay": "2026-09-30"}))
        summary = await async_client.workers.offboarding("w-1", last_day="2026-09-30")
        assert query_pairs(server.last) == {"lastDay": "2026-09-30"}
        assert summary.last_day == "2026-09-30"

        server.set_response(json_response(200, {"links": [LINK]}))
        links = await async_client.workers.links(connector="hubspot")
        assert links[0].connector == "hubspot"

        server.set_response(json_response(200, LINK))
        link = await async_client.workers.link("w-1", "hubspot", LinkWorkerInput(vendor_user_id="owner_42"))
        assert server.last.method == "PUT"
        assert link.vendor_user_id == "owner_42"

        server.set_response(empty_response(204))
        await async_client.workers.unlink("w-1", "hubspot")
        assert server.last.method == "DELETE"

        server.set_response(json_response(200, {"asOf": "2026-09-26", "skills": []}))
        expiring = await async_client.skills.expiring(within_days=7)
        assert query_pairs(server.last) == {"withinDays": "7"}
        assert expiring.skills == []

        server.set_response(json_response(200, TEAM_TIME))
        report = await async_client.team.time(TeamTimeQuery(team_id="team_1"))
        assert query_pairs(server.last) == {"teamId": "team_1"}
        assert report.workers[0].worker_id == "w-1"

    asyncio.run(scenario())
