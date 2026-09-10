"""Worker management: upsert, list, queue inspection, and removal."""

from __future__ import annotations

import pytest

from fivexer import CLEAR, FivexerApiError, PatchWorker, UpsertWorker, WorkerSkillAssignment
from tests.conftest import empty_response, json_response, read_body


def test_upserting_a_worker_without_an_id_gets_one_assigned(server, client):
    server.set_response(json_response(200, {"id": "w_abc"}))

    worker_id = client.workers.upsert()

    assert worker_id == "w_abc"
    assert read_body(server.last) == {}
    assert server.last.url.path == "/v1/workers"


def test_upserting_a_worker_with_tags_and_routing_weights_serializes_them(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", tags=["english", "billing"], routing_weights={"english": 100}))

    assert read_body(server.last) == {
        "id": "agent_1",
        "tags": ["english", "billing"],
        "routingWeights": {"english": 100},
    }


def test_upserting_a_field_worker_includes_geo_fields(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(
        UpsertWorker(id="agent_1", ip="203.0.113.5", latitude=59.4, longitude=24.7, max_travel_distance_km=15)
    )

    body = read_body(server.last)
    assert body == {
        "id": "agent_1",
        "ip": "203.0.113.5",
        "latitude": 59.4,
        "longitude": 24.7,
        "maxTravelDistanceKm": 15,
    }


def test_listing_workers_returns_ids_and_count(server, client):
    server.set_response(json_response(200, {"workers": ["agent_1", "agent_2"], "count": 2}))

    result = client.workers.list()

    assert result.workers == ["agent_1", "agent_2"]
    assert result.count == 2


def test_inspecting_a_worker_queue_returns_their_task_ids(server, client):
    server.set_response(json_response(200, {"workerId": "agent_1", "taskIds": ["task_8fk2", "task_9"]}))

    queue = client.workers.queue("agent_1")

    assert queue.worker_id == "agent_1"
    assert queue.task_ids == ["task_8fk2", "task_9"]
    assert server.last.url.path == "/v1/workers/agent_1/queue"


def test_removing_a_worker_returns_nothing(server, client):
    server.set_response(empty_response(204))

    result = client.workers.remove("agent_1")

    assert result is None
    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/workers/agent_1"


def test_valid_ids_with_dots_and_hyphens_pass_through_unchanged(server, client):
    server.set_response(json_response(200, {"workerId": "agent_1.2-3", "taskIds": []}))

    client.workers.queue("agent_1.2-3")

    # The platform only permits URL-safe IDs ([A-Za-z0-9_.-]), so valid ids reach the
    # server path verbatim.
    assert server.last.url.path == "/v1/workers/agent_1.2-3/queue"


def test_upserting_a_worker_at_the_plan_limit_raises(server, client):
    server.set_response(
        json_response(
            402,
            {"error": {"code": "plan_limit_exceeded", "message": "worker limit of 25 reached"}},
            headers={"x-quota-workers-limit": "25", "x-quota-workers-remaining": "0"},
        )
    )

    with pytest.raises(FivexerApiError) as exc:
        client.workers.upsert(UpsertWorker(id="agent_1"))
    assert exc.value.status_code == 402
    assert exc.value.code == "plan_limit_exceeded"


def test_upserting_a_worker_sends_their_language(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", locale="et"))

    assert read_body(server.last) == {"id": "agent_1", "locale": "et"}


def test_clearing_a_workers_language_sends_an_explicit_null(server, client):
    """Absent and null differ here: omitting the field leaves the stored language alone, so
    falling back to the workspace default has to be said explicitly."""
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", locale=CLEAR))

    assert read_body(server.last) == {"id": "agent_1", "locale": None}


def test_patching_a_worker_can_set_and_clear_the_language(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))
    client.workers.patch("agent_1", PatchWorker(locale="et"))
    assert read_body(server.last) == {"locale": "et"}

    server.set_response(json_response(200, {"id": "agent_1"}))
    client.workers.patch("agent_1", PatchWorker(locale=CLEAR))
    assert read_body(server.last) == {"locale": None}


def test_a_skill_assignment_carries_its_validity_window(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(
        UpsertWorker(
            id="agent_1",
            skills=[
                WorkerSkillAssignment(
                    skill_id="sk_forklift", level=4, valid_from="2026-01-01", valid_until="2026-12-31"
                )
            ],
        )
    )

    assert read_body(server.last)["skills"] == [
        {"skillId": "sk_forklift", "level": 4, "validFrom": "2026-01-01", "validUntil": "2026-12-31"}
    ]


def test_clearing_a_skills_expiry_sends_an_explicit_null(server, client):
    """A licence that stops expiring is a real edit, and only null can say it."""
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(
        UpsertWorker(id="agent_1", skills=[WorkerSkillAssignment(skill_id="sk_forklift", level=4, valid_until=CLEAR)])
    )

    assert read_body(server.last)["skills"] == [{"skillId": "sk_forklift", "level": 4, "validUntil": None}]


def test_clearing_a_skills_start_date_sends_an_explicit_null(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(
        UpsertWorker(id="agent_1", skills=[WorkerSkillAssignment(skill_id="sk_forklift", level=4, valid_from=CLEAR)])
    )

    assert read_body(server.last)["skills"] == [{"skillId": "sk_forklift", "level": 4, "validFrom": None}]


def test_worker_detail_reads_locale_and_skill_validity(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "agent_1",
                "locale": "et",
                "skills": [
                    {
                        "skillId": "sk_forklift",
                        "key": "forklift",
                        "name": "Forklift",
                        "level": 4,
                        "weight": 80,
                        "validFrom": "2026-01-01",
                        "validUntil": "2026-12-31",
                    }
                ],
            },
        )
    )

    worker = client.workers.get("agent_1")

    assert worker.locale == "et"
    assert worker.skills is not None
    assert (worker.skills[0].valid_from, worker.skills[0].valid_until) == ("2026-01-01", "2026-12-31")


def test_worker_detail_without_a_locale_reads_none(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    assert client.workers.get("agent_1").locale is None
