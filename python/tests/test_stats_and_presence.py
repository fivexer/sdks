"""Historical stats and the supervisor views of presence and breaks.

All of these need the control plane; a data-plane-only deployment answers 501, which callers
have to be able to tell apart from a real failure.
"""

from __future__ import annotations

import pytest

from fivexer import FivexerApiError, StatsWindowQuery
from tests.conftest import json_response, query_pairs


def test_the_timeseries_reports_empty_buckets_as_gaps_not_zeros(server, client):
    # An hour with no completions has no average wait — reporting 0ms would be a lie.
    server.set_response(
        json_response(
            200,
            {
                "from": "2026-07-23T00:00:00.000Z",
                "to": "2026-07-24T00:00:00.000Z",
                "bucket": "hour",
                "buckets": [
                    {
                        "bucketStart": "2026-07-23T00:00:00.000Z",
                        "completed": 12,
                        "cancelled": 1,
                        "avgWaitMs": 4200,
                        "p50WaitMs": 3000,
                        "p95WaitMs": 11000,
                        "avgHandleMs": 90000,
                    },
                    {
                        "bucketStart": "2026-07-23T01:00:00.000Z",
                        "completed": 0,
                        "cancelled": 0,
                        "avgWaitMs": None,
                        "p50WaitMs": None,
                        "p95WaitMs": None,
                        "avgHandleMs": None,
                    },
                ],
            },
        )
    )

    result = client.history.timeseries(
        StatsWindowQuery(from_="2026-07-23T00:00:00.000Z", to="2026-07-24T00:00:00.000Z", bucket="hour")
    )

    assert query_pairs(server.last) == {
        "from": "2026-07-23T00:00:00.000Z",
        "to": "2026-07-24T00:00:00.000Z",
        "bucket": "hour",
    }
    assert result.buckets[0].p95_wait_ms == 11000
    assert result.buckets[1].avg_wait_ms is None


def test_asking_for_the_default_window_sends_no_parameters(server, client):
    server.set_response(json_response(200, {"from": "a", "to": "b", "bucket": "hour", "buckets": []}))

    assert client.history.timeseries().buckets == []
    assert query_pairs(server.last) == {}


def test_worker_productivity_is_reported_per_worker_for_the_window(server, client):
    server.set_response(
        json_response(
            200,
            {
                "from": "2026-07-23T00:00:00.000Z",
                "to": "2026-07-24T00:00:00.000Z",
                "workers": [
                    {
                        "workerId": "agent_1",
                        "completed": 12,
                        "cancelled": 1,
                        "avgWaitMs": 4200,
                        "avgHandleMs": 90000,
                    }
                ],
            },
        )
    )

    result = client.history.workers(StatsWindowQuery(from_="2026-07-23T00:00:00.000Z"))

    assert server.last.url.path == "/v1/stats/workers"
    assert query_pairs(server.last) == {"from": "2026-07-23T00:00:00.000Z"}
    assert result.workers[0].completed == 12


def test_history_on_a_data_plane_only_deployment_is_reported_as_unavailable(server, client):
    # 501 here is a deployment fact, not a bug — the error code has to say which.
    server.set_response(
        json_response(
            501,
            {"error": {"code": "history_unavailable", "message": "historical stats require the control plane"}},
        )
    )

    with pytest.raises(FivexerApiError) as excinfo:
        client.history.timeseries()

    assert excinfo.value.code == "history_unavailable"
    assert excinfo.value.status_code == 501


def test_the_supervisor_presence_view_separates_paused_from_on_break(server, client):
    # Paused is an operator action; on-break is the worker's own. They must not be conflated.
    server.set_response(
        json_response(
            200,
            {
                "workers": [
                    {"workerId": "agent_1", "label": "Ada", "status": "working"},
                    {
                        "workerId": "agent_2",
                        "label": "Grace",
                        "status": "on-break",
                        "breakStartedAt": "2026-07-24T11:30:00.000Z",
                        "breakReason": "lunch",
                    },
                    {"workerId": "agent_3", "label": "Alan", "status": "paused"},
                ],
                "counts": {"working": 1, "onBreak": 1, "paused": 1, "total": 3},
            },
        )
    )

    presence = client.team.presence()

    assert server.last.url.path == "/v1/team/presence"
    assert (presence.counts.paused, presence.counts.on_break) == (1, 1)
    assert presence.workers[1].break_reason == "lunch"


def test_break_metrics_roll_up_per_worker_for_a_window(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workers": [
                    {
                        "workerId": "agent_2",
                        "label": "Grace",
                        "count": 2,
                        "totalBreakMs": 2700000,
                        "longestBreakMs": 1800000,
                        "active": True,
                    }
                ],
                "totalBreakMs": 2700000,
                "breakCount": 2,
                "activeCount": 1,
            },
        )
    )

    metrics = client.breaks.metrics(from_="2026-07-24T00:00:00.000Z", to="2026-07-24T23:59:59.999Z")

    assert query_pairs(server.last) == {
        "from": "2026-07-24T00:00:00.000Z",
        "to": "2026-07-24T23:59:59.999Z",
    }
    assert metrics.active_count == 1
    assert metrics.workers[0].longest_break_ms == 1800000


def test_break_metrics_default_to_today(server, client):
    server.set_response(
        json_response(200, {"workers": [], "totalBreakMs": 0, "breakCount": 0, "activeCount": 0})
    )

    assert client.breaks.metrics().break_count == 0
    assert query_pairs(server.last) == {}
