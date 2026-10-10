"""Fields the TypeScript SDK already reads and these models had not caught up with:
time-entry roles, worker photos, break coverage warnings and the leave settlement on
offboarding. Each test pins the wire name and the absent/null distinction."""

from __future__ import annotations

from fivexer import (
    CorrectTimeEntryInput,
    CreateTimeEntryInput,
    PublicWorkerIdentity,
    RoleStretchInput,
    WorkerBreakStarted,
    WorkerMe,
    WorkerOffboardingSummary,
    WorkerTimeEntry,
)

WARNING = {"shiftInstanceId": "si_1", "shiftName": "Early", "remaining": 1, "required": 2, "kind": "headcount"}


def test_a_time_entry_reads_its_roles_and_keeps_none_for_a_shift_without():
    entry = WorkerTimeEntry.from_json(
        {
            "type": "shift",
            "roles": [
                {
                    "positionId": "pos_1",
                    "startedAt": "2026-10-01T06:00:00Z",
                    "endedAt": "2026-10-01T10:00:00Z",
                    "label": "Forklift",
                },
                {"positionId": None, "startedAt": "2026-10-01T10:00:00Z", "endedAt": None, "label": None},
            ],
        }
    )
    assert entry.roles is not None
    assert [r.label for r in entry.roles] == ["Forklift", None]
    assert entry.roles[1].ended_at is None
    assert WorkerTimeEntry.from_json({"type": "shift"}).roles is None


def test_time_entry_writes_send_roles_only_when_given():
    roles = [RoleStretchInput(position_id="pos_1", started_at="2026-10-01T06:00:00Z", ended_at="2026-10-01T10:00:00Z")]
    created = CreateTimeEntryInput(started_at="a", ended_at="b", note="forgot", roles=roles).to_json()
    assert created["roles"] == [
        {"positionId": "pos_1", "startedAt": "2026-10-01T06:00:00Z", "endedAt": "2026-10-01T10:00:00Z"}
    ]
    assert "roles" not in CreateTimeEntryInput(started_at="a", ended_at="b", note="n").to_json()
    corrected = CorrectTimeEntryInput(note="wrong role", roles=roles).to_json()
    assert corrected["roles"][0]["positionId"] == "pos_1"
    assert "roles" not in CorrectTimeEntryInput(note="n").to_json()


def test_photos_and_break_warnings_on_the_worker():
    me = WorkerMe.from_json(
        {
            "workerId": "w1",
            "photoUrl": "/v1/worker-photos/abc",
            "photoUploads": True,
            "breakCoverageWarnings": [WARNING],
        }
    )
    assert me.photo_url == "/v1/worker-photos/abc"
    assert me.photo_uploads is True
    assert me.break_coverage_warnings[0].shift_name == "Early"
    bare = WorkerMe.from_json({"workerId": "w1"})
    assert bare.photo_url is None and bare.photo_uploads is False and bare.break_coverage_warnings == []
    identity = PublicWorkerIdentity.from_json({"id": "wi_1", "photoUrl": "/v1/worker-photos/abc"})
    assert identity.photo_url == "/v1/worker-photos/abc"


def test_a_break_reports_the_shifts_it_leaves_short():
    started = WorkerBreakStarted.from_json(
        {"workerId": "w1", "since": "x", "onBreak": True, "coverageWarnings": [WARNING]}
    )
    assert started.coverage_warnings[0].remaining == 1
    assert started.coverage_warnings[0].kind == "headcount"


def test_offboarding_reads_the_leave_settlement():
    summary = WorkerOffboardingSummary.from_json(
        {
            "workerId": "w1",
            "lastDay": "2026-10-31",
            "leavePayout": {"paidOutMinutes": 960, "recoveredMinutes": 0},
            "futureLeave": 2,
            "leaveSettlementError": None,
        }
    )
    assert summary.leave_payout is not None and summary.leave_payout.paid_out_minutes == 960
    assert summary.future_leave == 2
    assert summary.leave_settlement_error is None
    older = WorkerOffboardingSummary.from_json({"workerId": "w1", "lastDay": "2026-10-31"})
    assert older.leave_payout is None and older.future_leave == 0
