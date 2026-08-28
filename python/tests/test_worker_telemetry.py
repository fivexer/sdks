"""Recorded working time: the shift log, per-worker analytics, and the liveness contract.

Availability is *state* the matcher owns; these operations read the durable
*record* beside it — the working-time record an EU employer is obliged to keep
(CJEU C-55/18). Worked time is shift time minus overlapping breaks, because
breaks are time inside a shift.

They are measured facts, not a rating: nothing here ranks a person, and none of
it feeds back into matching.
"""

from __future__ import annotations

from fivexer import StatsWindowQuery, WorkerStatsQuery
from tests.conftest import json_response, query_pairs, read_body


def test_worker_productivity_merges_throughput_response_and_worked_time(server, client):
    server.set_response(
        json_response(
            200,
            {
                "from": "2026-08-19T00:00:00.000Z",
                "to": "2026-08-26T00:00:00.000Z",
                "workers": [
                    {
                        "workerId": "w-1",
                        "completed": 12,
                        "cancelled": 1,
                        "avgWaitMs": 4200,
                        "avgHandleMs": 90000,
                        "p50HandleMs": 80000,
                        "p95HandleMs": 160000,
                        "offered": 20,
                        "accepted": 15,
                        "rejected": 4,
                        "failed": 1,
                        "expired": 1,
                        "released": 0,
                        "acceptanceRate": 0.75,
                        "onShiftMs": 28_800_000,
                        "breakMs": 3_600_000,
                        "workingMs": 25_200_000,
                        "shiftCount": 5,
                        "utilization": 0.43,
                    }
                ],
            },
        )
    )

    result = client.history.workers(WorkerStatsQuery(from_="2026-08-19T00:00:00.000Z", team_id="team_1"))

    row = result.workers[0]
    assert row.worker_id == "w-1"
    assert row.p50_handle_ms == 80000
    assert row.acceptance_rate == 0.75
    # Breaks are time inside the shift, so worked time is the difference.
    assert row.working_ms == row.on_shift_ms - row.break_ms
    assert row.utilization == 0.43
    assert query_pairs(server.last)["teamId"] == "team_1"


def test_a_worker_who_only_sat_on_shift_reports_zeros_rather_than_going_missing(server, client):
    # The server returns the union of everyone it knows about, so someone who
    # finished nothing still has a row — an absent row would read as "no such
    # worker", which is a different statement.
    server.set_response(
        json_response(
            200,
            {
                "from": "2026-08-25T00:00:00.000Z",
                "to": "2026-08-26T00:00:00.000Z",
                "workers": [{"workerId": "w-idle", "completed": 0, "cancelled": 0, "onShiftMs": 7_200_000}],
            },
        )
    )

    row = client.history.workers().workers[0]
    assert row.completed == 0
    assert row.on_shift_ms == 7_200_000
    # No offers yet: a rate over nothing is unknown, not zero.
    assert row.acceptance_rate is None
    assert row.utilization is None


def test_worked_time_and_counters_ride_only_on_day_buckets(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "from": "2026-08-24T00:00:00.000Z",
                "to": "2026-08-26T00:00:00.000Z",
                "bucket": "day",
                "buckets": [
                    {
                        "bucketStart": "2026-08-24T00:00:00.000Z",
                        "completed": 6,
                        "cancelled": 0,
                        "avgWaitMs": 3000,
                        "p50WaitMs": 2500,
                        "p95WaitMs": 9000,
                        "avgHandleMs": 60000,
                        "onShiftMs": 28_800_000,
                        "breakMs": 1_800_000,
                        "workingMs": 27_000_000,
                        "offered": 9,
                        "accepted": 7,
                        "rejected": 2,
                    },
                    # An hour-grained server, or a day with no shift, simply omits
                    # them — absent means "not measured at this resolution", which
                    # is why they parse to None rather than 0.
                    {"bucketStart": "2026-08-25T00:00:00.000Z", "completed": 0, "cancelled": 0},
                ],
            },
        )
    )

    result = client.history.worker_timeseries("w-1", StatsWindowQuery(bucket="day"))

    assert result.buckets[0].working_ms == 27_000_000
    assert result.buckets[0].offered == 9
    assert result.buckets[1].working_ms is None
    assert result.buckets[1].offered is None
    assert server.last.url.path == "/v1/stats/workers/w-1/timeseries"


def test_worker_metrics_reports_today_and_a_rolling_window(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "today": {
                    "since": "2026-08-26T00:00:00.000Z",
                    "completedTasks": 3,
                    "shiftCount": 1,
                    "onShiftMs": 14_400_000,
                    "breakCount": 1,
                    "totalBreakMs": 1_800_000,
                    "longestBreakMs": 1_800_000,
                    "workingMs": 12_600_000,
                },
                "window": {
                    "from": "2026-08-19T00:00:00.000Z",
                    "to": "2026-08-26T00:00:00.000Z",
                    "days": [{"day": "2026-08-25T00:00:00.000Z", "completed": 4}],
                    "medianWaitMs": 3000,
                    "medianCycleMs": 60000,
                    "shiftCount": 5,
                    "onShiftMs": 100_000_000,
                    "breakMs": 5_000_000,
                    "workingMs": 95_000_000,
                    "offered": 20,
                    "accepted": 15,
                    "rejected": 4,
                    "completed": 12,
                    "failed": 1,
                    "expired": 1,
                    "released": 0,
                    "acceptanceRate": 0.75,
                },
            },
        )
    )

    metrics = client.workers.metrics("w-1", window="7d")

    assert metrics.today.working_ms == 12_600_000
    assert metrics.window.median_cycle_ms == 60000
    assert metrics.window.days[0].completed == 4
    assert query_pairs(server.last)["window"] == "7d"


def test_absent_history_is_null_days_not_an_empty_list(server, client):
    # A deployment that keeps no task archive answers `days: null`. That is a
    # different statement from "history exists and this worker finished nothing"
    # — every port preserves the distinction rather than flattening it.
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "today": {"since": "2026-08-26T00:00:00.000Z"},
                "window": {"from": "2026-08-19T00:00:00.000Z", "to": "2026-08-26T00:00:00.000Z", "days": None},
            },
        )
    )
    assert client.workers.metrics("w-1").window.days is None

    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "today": {"since": "2026-08-26T00:00:00.000Z"},
                "window": {"from": "2026-08-19T00:00:00.000Z", "to": "2026-08-26T00:00:00.000Z", "days": []},
            },
        )
    )
    assert client.workers.metrics("w-1").window.days == []


def test_the_time_log_distinguishes_a_shift_a_break_and_an_automatic_clock_out(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "from": "2026-08-19T00:00:00.000Z",
                "to": "2026-08-26T00:00:00.000Z",
                "entries": [
                    {
                        "type": "shift",
                        "startedAt": "2026-08-25T08:00:00.000Z",
                        "endedAt": "2026-08-25T16:00:00.000Z",
                        "durationMs": 28_800_000,
                        "source": "portal",
                        "endReason": "manual",
                    },
                    {
                        "type": "break",
                        "startedAt": "2026-08-25T12:00:00.000Z",
                        "endedAt": "2026-08-25T12:30:00.000Z",
                        "durationMs": 1_800_000,
                        "reason": "Lunch",
                    },
                    {
                        # An unattended worker that went silent past its liveness
                        # contract: the platform closed the shift for it.
                        "type": "shift",
                        "startedAt": "2026-08-24T09:00:00.000Z",
                        "endedAt": "2026-08-24T09:20:00.000Z",
                        "durationMs": 1_200_000,
                        "source": "portal",
                        "endReason": "timeout",
                    },
                ],
                "totals": {
                    "shiftCount": 2,
                    "onShiftMs": 30_000_000,
                    "breakCount": 1,
                    "breakMs": 1_800_000,
                    "workingMs": 28_200_000,
                },
            },
        )
    )

    log = client.workers.time_entries("w-1", from_="2026-08-19T00:00:00.000Z")

    assert [entry.type for entry in log.entries] == ["shift", "break", "shift"]
    assert log.entries[1].reason == "Lunch"
    assert log.entries[2].end_reason == "timeout"
    assert log.totals.working_ms == log.totals.on_shift_ms - log.totals.break_ms
    assert query_pairs(server.last)["from"] == "2026-08-19T00:00:00.000Z"


def test_break_metrics_can_be_narrowed_to_one_crew_or_one_worker(server, client):
    server.set_response(json_response(200, {"workers": [], "totalBreakMs": 0, "breakCount": 0, "activeCount": 0}))

    client.breaks.metrics(from_="2026-08-26T00:00:00.000Z", team_id="team_1", worker_id="w-1")

    pairs = query_pairs(server.last)
    assert pairs["teamId"] == "team_1"
    assert pairs["workerId"] == "w-1"


def test_presence_can_be_scoped_to_one_crew(server, client):
    server.set_response(
        json_response(200, {"workers": [], "counts": {"working": 0, "onBreak": 0, "paused": 0, "total": 0}})
    )

    client.team.presence("team_1")

    assert query_pairs(server.last)["teamId"] == "team_1"


def test_only_an_unattended_worker_declares_a_liveness_contract(server, worker):
    server.set_response(json_response(200, {"workerId": "w-1", "available": True}))
    worker.set_availability(True, stale_after_ms=120_000)
    assert read_body(server.last) == {"available": True, "staleAfterMs": 120_000}

    # A human portal sends nothing extra: someone working away from their phone
    # is not a crashed process and must never be clocked out for it.
    server.set_response(json_response(200, {"workerId": "w-1", "available": True}))
    worker.set_availability(True)
    assert read_body(server.last) == {"available": True}

    # And it is meaningless when going off shift, so it is never sent there.
    server.set_response(json_response(200, {"workerId": "w-1", "available": False}))
    worker.set_availability(False, stale_after_ms=120_000)
    assert read_body(server.last) == {"available": False}


def test_a_worker_reads_the_same_record_about_themselves(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "from": "2026-08-19T00:00:00.000Z",
                "to": "2026-08-26T00:00:00.000Z",
                "entries": [
                    {
                        "type": "shift",
                        "startedAt": "2026-08-25T08:00:00.000Z",
                        "endedAt": None,
                        "durationMs": 3_600_000,
                        "source": "portal",
                        "endReason": None,
                    }
                ],
                "totals": {
                    "shiftCount": 1,
                    "onShiftMs": 3_600_000,
                    "breakCount": 0,
                    "breakMs": 0,
                    "workingMs": 3_600_000,
                },
            },
        )
    )

    log = worker.time_entries()

    assert server.last.url.path == "/v1/portal/me/time-entries"
    # An open shift has no end and is measured to now.
    assert log.entries[0].ended_at is None
    assert log.totals.working_ms == 3_600_000


def test_todays_metrics_carry_shift_figures_when_the_server_records_them(server, worker):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "since": "2026-08-26T00:00:00.000Z",
                "completedTasks": 3,
                "breakCount": 1,
                "totalBreakMs": 1_800_000,
                "longestBreakMs": 1_800_000,
                "workingMs": 12_600_000,
                "onShiftMs": 14_400_000,
                "shiftCount": 1,
            },
        )
    )

    today = worker.metrics_today()
    assert today.on_shift_ms == 14_400_000
    assert today.shift_count == 1

    # Additive fields: an older server omits them and the client still parses.
    server.set_response(
        json_response(
            200,
            {
                "workerId": "w-1",
                "since": "2026-08-26T00:00:00.000Z",
                "completedTasks": 3,
                "breakCount": 1,
                "totalBreakMs": 1_800_000,
                "longestBreakMs": 1_800_000,
                "workingMs": 12_600_000,
            },
        )
    )
    legacy = worker.metrics_today()
    assert legacy.on_shift_ms == 0
    assert legacy.shift_count == 0
