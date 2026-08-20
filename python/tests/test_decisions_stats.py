"""Decision traces (explainability) and workspace stats."""

from __future__ import annotations

from fivexer import ListDecisionsQuery
from tests.conftest import query_pairs


def test_querying_decisions_by_task_returns_candidates_with_scores(server, client):
    from tests.conftest import json_response

    server.set_response(
        json_response(
            200,
            {
                "decisions": [
                    {
                        "id": "dec_1",
                        "taskId": "task_8fk2",
                        "workerId": "agent_1",
                        "matchedAt": 1750000000000,
                        "mode": "best-match",
                        "candidates": [
                            {"workerId": "agent_1", "eligible": True, "chosen": True, "score": 101},
                            {"workerId": "agent_2", "eligible": False, "chosen": False, "score": 0},
                        ],
                    }
                ]
            },
        )
    )

    decisions = client.decisions.list(ListDecisionsQuery(task_id="task_8fk2"))

    assert len(decisions) == 1
    dec = decisions[0]
    assert dec.id == "dec_1"
    assert dec.task_id == "task_8fk2"
    assert dec.worker_id == "agent_1"
    assert dec.matched_at == 1750000000000
    assert dec.mode == "best-match"
    assert len(dec.candidates) == 2
    assert dec.candidates[0].worker_id == "agent_1"
    assert dec.candidates[0].detail == {"eligible": True, "chosen": True, "score": 101}
    assert query_pairs(server.last) == {"taskId": "task_8fk2"}


def test_querying_decisions_by_worker_and_limit_passes_them(server, client):
    from tests.conftest import json_response

    server.set_response(json_response(200, {"decisions": []}))

    client.decisions.list(ListDecisionsQuery(worker_id="agent_1", limit=10))

    assert query_pairs(server.last) == {"workerId": "agent_1", "limit": "10"}


def test_decisions_default_to_an_empty_list_when_none_exist(server, client):
    from tests.conftest import json_response

    server.set_response(json_response(200, {"decisions": []}))

    assert client.decisions.list() == []


def test_workspace_stats_reports_queue_depth_meter_and_plan(server, client):
    from tests.conftest import json_response

    server.set_response(
        json_response(
            200,
            {
                "plan": "pro",
                "tasks": {"queued": 3, "pending": 1, "accepted": 2},
                "workers": 5,
                "meter": {"period": "2026-07", "matchedTasks": 421, "includedTasksPerMonth": 50000},
            },
        )
    )

    stats = client.stats()

    assert stats.plan == "pro"
    assert stats.tasks == {"queued": 3, "pending": 1, "accepted": 2}
    assert stats.workers == 5
    assert stats.meter_matched_tasks == 421
    assert stats.meter_included_tasks_per_month == 50000
    assert stats.meter_period == "2026-07"
    assert server.last.url.path == "/v1/stats"
