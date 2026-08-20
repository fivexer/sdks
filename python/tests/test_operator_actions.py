"""Operator overrides: assigning work by hand, repricing it, and taking workers off the line.

These bypass or reshape normal matching, so they are the operations most likely to be reached
for during an incident and the ones where a wrong request body does visible damage.
"""

from __future__ import annotations

import pytest

from fivexer import FivexerApiError, PatchWorker, RequiredSkill, SuggestWorkers, WorkerSkillAssignment
from tests.conftest import json_response, read_body

# ---- assigning a task by hand --------------------------------------------


def test_assigning_a_queued_task_reports_no_previous_worker(server, client):
    server.set_response(
        json_response(
            200, {"id": "task_8fk2", "status": "pending", "workerId": "agent_1", "previousWorkerId": None}
        )
    )

    result = client.tasks.assign("task_8fk2", "agent_1")

    assert server.last.url.path == "/v1/tasks/task_8fk2/assign"
    assert read_body(server.last) == {"workerId": "agent_1"}
    assert result.worker_id == "agent_1"
    assert result.previous_worker_id is None


def test_reassigning_a_pending_task_names_the_worker_it_was_taken_from(server, client):
    # Knowing who lost the task is what lets an operator explain the move afterwards.
    server.set_response(
        json_response(
            200,
            {"id": "task_8fk2", "status": "pending", "workerId": "agent_2", "previousWorkerId": "agent_1"},
        )
    )

    result = client.tasks.assign("task_8fk2", "agent_2")

    assert result.previous_worker_id == "agent_1"


def test_forcing_an_assignment_sends_the_override_flag(server, client):
    server.set_response(
        json_response(
            200, {"id": "task_8fk2", "status": "pending", "workerId": "agent_1", "previousWorkerId": None}
        )
    )

    client.tasks.assign("task_8fk2", "agent_1", force=True)

    assert read_body(server.last) == {"workerId": "agent_1", "force": True}


def test_an_unforced_assignment_respects_the_backlog_cap(server, client):
    server.set_response(
        json_response(
            409, {"error": {"code": "worker_backlog_full", "message": "agent_1 is at its backlog limit"}}
        )
    )

    with pytest.raises(FivexerApiError) as excinfo:
        client.tasks.assign("task_8fk2", "agent_1")

    assert excinfo.value.code == "worker_backlog_full"
    assert excinfo.value.status_code == 409


def test_repricing_a_task_returns_its_new_priority(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "priority": 95}))

    result = client.tasks.set_priority("task_8fk2", 95)

    assert server.last.method == "PATCH"
    assert read_body(server.last) == {"priority": 95}
    assert result.priority == 95


# ---- dry-run scoring ------------------------------------------------------


def test_suggesting_workers_explains_why_each_one_is_or_is_not_eligible(server, client):
    server.set_response(
        json_response(
            200,
            {
                "tags": ["english", "billing"],
                "priority": 90,
                "workers": [
                    {
                        "workerId": "agent_1",
                        "eligible": True,
                        "score": 180,
                        "effectivePriority": 90,
                        "reasons": [{"tag": "english", "weight": 100}],
                    },
                    {
                        "workerId": "agent_2",
                        "eligible": False,
                        "score": 0,
                        "effectivePriority": 90,
                        "reasons": [{"vetoed": True}],
                    },
                ],
            },
        )
    )

    result = client.tasks.suggest_workers(
        SuggestWorkers(
            tags=["english", "billing"],
            priority=90,
            required_skills=[RequiredSkill(skill_id="skl_1", min_level=3)],
            limit=2,
        )
    )

    assert server.last.url.path == "/v1/tasks/suggest-workers"
    assert read_body(server.last) == {
        "tags": ["english", "billing"],
        "priority": 90,
        "requiredSkills": [{"skillId": "skl_1", "minLevel": 3}],
        "limit": 2,
    }
    assert [(w.worker_id, w.eligible) for w in result.workers] == [("agent_1", True), ("agent_2", False)]
    assert result.workers[0].reasons == [{"tag": "english", "weight": 100}]
    assert result.workers[1].score == 0


def test_suggesting_workers_creates_no_task(server, client):
    # The whole point is to preview routing without side effects.
    server.set_response(json_response(200, {"tags": [], "priority": 0, "workers": []}))

    client.tasks.suggest_workers(SuggestWorkers(tags=["english"]))

    assert [r.url.path for r in server.requests] == ["/v1/tasks/suggest-workers"]


# ---- worker detail and availability ---------------------------------------


def test_reading_a_worker_shows_their_load_against_their_cap(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "agent_1",
                "tags": ["english", "billing"],
                "routingWeights": {"english": 100, "billing": 50},
                "skills": [
                    {
                        "skillId": "skl_1",
                        "key": "refunds",
                        "name": "Refunds",
                        "level": 4,
                        "weightOverride": None,
                        "weight": 80,
                    }
                ],
                "maxBacklogSize": 5,
                "available": True,
                "queueDepth": 2,
            },
        )
    )

    detail = client.workers.get("agent_1")

    assert server.last.url.path == "/v1/workers/agent_1"
    assert (detail.queue_depth, detail.max_backlog_size) == (2, 5)
    assert detail.skills is not None
    assert detail.skills[0].name == "Refunds"


def test_patching_a_worker_changes_only_the_fields_supplied(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    worker_id = client.workers.patch(
        "agent_1", PatchWorker(skills=[WorkerSkillAssignment(skill_id="skl_1", level=5)])
    )

    assert server.last.method == "PATCH"
    assert read_body(server.last) == {"skills": [{"skillId": "skl_1", "level": 5}]}
    assert worker_id == "agent_1"


def test_pausing_a_worker_keeps_their_backlog(server, client):
    # "Back in ten minutes": stop matching new work, but leave what they already hold.
    server.set_response(json_response(200, {"id": "agent_1", "available": False}))

    result = client.workers.set_availability("agent_1", False)

    assert server.last.url.path == "/v1/workers/agent_1/availability"
    assert read_body(server.last) == {"available": False}
    assert result.available is False
    assert result.released_task_ids is None


def test_pausing_and_releasing_reports_which_tasks_were_requeued(server, client):
    # "Gone for the day": the unaccepted backlog goes back to the queue for others.
    server.set_response(
        json_response(
            200, {"id": "agent_1", "available": False, "releasedTaskIds": ["task_8fk2", "task_9aa3"]}
        )
    )

    result = client.workers.set_availability("agent_1", False, release_backlog=True)

    assert read_body(server.last) == {"available": False, "releaseBacklog": True}
    assert result.released_task_ids == ["task_8fk2", "task_9aa3"]


def test_resuming_a_worker_never_mentions_backlog_release(server, client):
    # The API rejects releaseBacklog on resume, so the SDK must not send it either way.
    server.set_response(json_response(200, {"id": "agent_1", "available": True}))

    client.workers.set_availability("agent_1", True)

    assert read_body(server.last) == {"available": True}


def test_asking_to_release_a_backlog_while_resuming_is_rejected_by_the_api(server, client):
    server.set_response(
        json_response(
            400, {"error": {"code": "invalid_body", "message": "releaseBacklog is only valid when pausing"}}
        )
    )

    with pytest.raises(FivexerApiError) as excinfo:
        client.workers.set_availability("agent_1", True, release_backlog=True)

    assert excinfo.value.code == "invalid_body"
