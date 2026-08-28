"""Bulk create, the dry-run check, the escalation ladder, and the parked/scheduled views.

What these share is that they are the *operational* surface — reached for when a queue is
misbehaving, not on the happy path. Two things are worth pinning hard: bulk create reports
partial success (so a caller that only reads the status code silently loses tasks), and unpark
takes reset flags that decide whether the next sweep parks the task straight back.
"""

from __future__ import annotations

import asyncio

from fivexer import CreateTask, QueueAuditQuery, UnparkTask
from tests.conftest import json_response, read_body

TASK = {
    "id": "task_8fk2",
    "status": "queued",
    "tags": ["english", "billing"],
    "priority": 90,
    "createdAt": 1_754_000_000_000,
}


# ---- bulk create ---------------------------------------------------------


def test_bulk_create_reports_partial_success_per_entry(server, client):
    # A 200 here does not mean everything was created. A caller that reads only the status
    # code loses the failures silently, so `failed` and the per-entry error must survive.
    server.set_response(
        json_response(
            200,
            {
                "created": 1,
                "failed": 1,
                "results": [
                    {"index": 0, "id": "task_1", "status": "queued"},
                    {"index": 1, "error": {"code": "validation_failed", "message": "tags required"}},
                ],
            },
        )
    )

    report = client.tasks.create_many([CreateTask(tags=["a"]), CreateTask(tags=[])])

    assert server.last.url.path == "/v1/tasks/bulk"
    assert read_body(server.last) == {"tasks": [{"tags": ["a"]}, {"tags": []}]}
    assert (report.created, report.failed) == (1, 1)
    assert report.results[0].ok is True
    assert report.results[0].id == "task_1"
    assert report.results[1].ok is False
    assert report.results[1].error.code == "validation_failed"


def test_a_bulk_entry_keeps_the_index_that_maps_it_back_to_the_input(server, client):
    # The caller's list is the only way to know *which* task failed; without `index` a
    # partial failure is unactionable.
    server.set_response(
        json_response(
            200,
            {
                "created": 0,
                "failed": 1,
                "results": [{"index": 7, "error": {"code": "plan_limit_exceeded", "message": "quota"}}],
            },
        )
    )

    report = client.tasks.create_many([CreateTask(tags=["a"])])

    assert report.results[0].index == 7


def test_bulk_create_rejects_a_raw_dict_with_a_clear_error(server, client):
    server.set_response(json_response(200, {"created": 0, "failed": 0, "results": []}))

    try:
        client.tasks.create_many([{"tags": ["a"]}])
    except TypeError as error:
        assert "CreateTask" in str(error)
    else:  # pragma: no cover - the guard is the point of the test
        raise AssertionError("expected a TypeError naming the model")


# ---- dry-run check -------------------------------------------------------


def test_check_reports_who_could_take_a_task_without_creating_it(server, client):
    server.set_response(
        json_response(
            200,
            {
                "issues": [{"severity": "warning", "code": "no_coverage", "message": "no welsh", "tag": "welsh"}],
                "eligibleWorkerCount": 0,
                "uncoveredTags": ["welsh"],
                "evaluatedAt": 1_754_000_000_000,
            },
        )
    )

    report = client.tasks.check(CreateTask(tags=["welsh"]))

    assert server.last.method == "POST"
    assert server.last.url.path == "/v1/tasks/check"
    assert report.eligible_worker_count == 0
    assert report.uncovered_tags == ["welsh"]
    assert report.issues[0].tag == "welsh"


def test_a_check_issue_without_a_tag_parses_as_none(server, client):
    server.set_response(
        json_response(
            200,
            {
                "issues": [{"severity": "error", "code": "bad_sla", "message": "completeWithinMs < 0"}],
                "eligibleWorkerCount": 3,
                "uncoveredTags": [],
                "evaluatedAt": 1,
            },
        )
    )

    assert client.tasks.check(CreateTask(tags=["a"])).issues[0].tag is None


# ---- ack / escalate / park -----------------------------------------------


def test_ack_stops_the_response_clock_without_starting_work(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "accepted"}))

    result = client.tasks.ack("task_8fk2", "agent_1")

    assert server.last.url.path == "/v1/tasks/task_8fk2/ack"
    assert read_body(server.last) == {"workerId": "agent_1"}
    assert result.status == "accepted"


def test_escalating_without_a_worker_sends_an_empty_body(server, client):
    server.set_response(
        json_response(200, {"id": "task_8fk2", "escalated": True, "parked": False, "escalationLevel": 1})
    )

    result = client.tasks.escalate("task_8fk2")

    assert read_body(server.last) == {}
    assert result.escalation_level == 1
    assert result.parked is False


def test_an_exhausted_ladder_reports_the_task_as_parked(server, client):
    # `escalated: False, parked: True` is the end of the ladder — the task left matching, and
    # this flag is the only thing that says so.
    server.set_response(
        json_response(200, {"id": "task_8fk2", "escalated": False, "parked": True, "escalationLevel": 3})
    )

    result = client.tasks.escalate("task_8fk2", "agent_1")

    assert read_body(server.last) == {"workerId": "agent_1"}
    assert result.parked is True


def test_parked_and_scheduled_views_carry_their_own_count(server, client):
    server.set_response(json_response(200, {"tasks": [TASK], "count": 1}))

    parked = client.tasks.parked()

    assert server.last.url.path == "/v1/tasks/parked"
    assert parked.count == 1
    assert parked.tasks[0].id == "task_8fk2"


def test_scheduled_tasks_are_a_separate_view_from_parked_ones(server, client):
    server.set_response(json_response(200, {"tasks": [{**TASK, "status": "scheduled"}], "count": 1}))

    scheduled = client.tasks.scheduled()

    assert server.last.url.path == "/v1/tasks/scheduled"
    assert scheduled.tasks[0].status == "scheduled"


def test_unparking_with_no_options_sends_an_empty_body(server, client):
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "queued"}))

    client.tasks.unpark("task_8fk2")

    assert read_body(server.last) == {}


def test_unpark_reset_flags_reach_the_wire_in_camel_case(server, client):
    # Leave a clock set and the next sweep parks the task again — these flags are the whole
    # reason unpark is not just a status change.
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "queued"}))

    client.tasks.unpark("task_8fk2", UnparkTask(reset_escalation=True, reset_sla=True))

    assert read_body(server.last) == {"resetEscalation": True, "resetSla": True}


def test_an_unset_unpark_flag_is_absent_not_false(server, client):
    # False would actively assert "do not reset"; absent leaves the server's default.
    server.set_response(json_response(200, {"id": "task_8fk2", "status": "queued"}))

    client.tasks.unpark("task_8fk2", UnparkTask(reset_schedule=False))

    assert read_body(server.last) == {"resetSchedule": False}


# ---- SLA stats and the queue audit ---------------------------------------


def test_sla_stats_default_to_the_whole_workspace(server, client):
    server.set_response(json_response(200, {"tag": None, "offers": 10, "acceptedInTime": 9}))

    stats = client.sla_stats()

    assert server.last.url.path == "/v1/stats/sla"
    assert server.last.url.params.get("tag") is None
    assert stats.tag is None
    assert stats.offers == 10


def test_sla_stats_for_one_tag_send_it_as_a_query_param(server, client):
    server.set_response(json_response(200, {"tag": "billing", "offers": 4, "acceptanceRate": 0.75}))

    stats = client.sla_stats("billing")

    assert server.last.url.params["tag"] == "billing"
    assert stats.acceptance_rate == 0.75


def test_no_offers_yet_leaves_acceptance_rate_none_rather_than_zero(server, client):
    # 0.0 means "everyone missed"; None means "nothing measured". Collapsing them turns a
    # cold start into a false alarm on a dashboard.
    server.set_response(json_response(200, {"tag": None, "offers": 0, "acceptanceRate": None}))

    assert client.sla_stats().acceptance_rate is None


def test_the_queue_audit_names_what_is_blocking_each_task(server, client):
    server.set_response(
        json_response(
            200,
            {
                "evaluatedAt": 1_754_000_000_000,
                "scanned": 2,
                "entries": [
                    {
                        "taskId": "task_8fk2",
                        "tags": ["welsh"],
                        "waitingMs": 90_000,
                        "eligibleWorkerCount": 0,
                        "uncoveredTags": ["welsh"],
                        "blockers": {"backlog_full": 2, "paused": 1},
                    }
                ],
                "sweepBacklog": {
                    "scheduleActivations": 1,
                    "scheduleMisses": 0,
                    "responseDeadlines": 4,
                    "completionDeadlines": 0,
                    "slaExpiries": 2,
                },
            },
        )
    )

    report = client.queue_audit(QueueAuditQuery(limit=10, min_waiting_ms=60_000, include_healthy=False))

    assert server.last.url.path == "/v1/stats/queue-audit"
    assert server.last.url.params["limit"] == "10"
    assert server.last.url.params["minWaitingMs"] == "60000"
    # Fastify parses the string form; Python's capitalised `False` would not round-trip.
    assert server.last.url.params["includeHealthy"] == "false"
    assert report.entries[0].blockers == {"backlog_full": 2, "paused": 1}
    assert report.sweep_backlog.response_deadlines == 4


def test_a_never_queued_task_has_a_null_wait_rather_than_zero(server, client):
    server.set_response(
        json_response(
            200,
            {
                "evaluatedAt": 1,
                "scanned": 1,
                "entries": [
                    {
                        "taskId": "t1",
                        "tags": [],
                        "waitingMs": None,
                        "eligibleWorkerCount": 1,
                        "uncoveredTags": [],
                        "blockers": {},
                    }
                ],
                "sweepBacklog": {},
            },
        )
    )

    report = client.queue_audit()

    assert report.entries[0].waiting_ms is None
    assert report.sweep_backlog.sla_expiries == 0


def test_the_queue_audit_defaults_to_no_query_params(server, client):
    server.set_response(json_response(200, {"evaluatedAt": 1, "scanned": 0, "entries": [], "sweepBacklog": {}}))

    client.queue_audit()

    assert dict(server.last.url.params) == {}


# ---- portal link and learning revert -------------------------------------


def test_the_portal_link_reports_a_disabled_portal_without_erroring(server, client):
    # A workspace with no published bundle is a normal state, not a failure.
    server.set_response(
        json_response(
            200,
            {
                "portalEnabled": False,
                "portalUrl": "https://5xer.com/portal/ws_1/",
                "exists": False,
                "version": None,
                "template": None,
                "publishedAt": None,
            },
        )
    )

    link = client.portal()

    assert server.last.url.path == "/v1/portal"
    assert link.portal_enabled is False
    assert link.version is None


def test_reverting_learned_weights_without_ids_sends_an_empty_body(server, client):
    server.set_response(json_response(200, {"reverted": ["agent_1"], "count": 1}))

    result = client.learning.revert_weights()

    assert server.last.url.path == "/v1/learning/weights/revert"
    assert read_body(server.last) == {}
    assert result.count == 1


def test_reverting_scoped_to_named_workers_sends_the_list(server, client):
    server.set_response(json_response(200, {"reverted": ["agent_1"], "count": 1}))

    client.learning.revert_weights(["agent_1", "agent_2"])

    assert read_body(server.last) == {"workerIds": ["agent_1", "agent_2"]}


def test_fetching_one_skill_by_id(server, client):
    server.set_response(
        json_response(200, {"id": "sk_1", "key": "welsh", "name": "Welsh", "createdAt": "2026-08-01T00:00:00Z"})
    )

    skill = client.skills.get("sk_1")

    assert server.last.url.path == "/v1/skills/sk_1"
    assert skill.key == "welsh"


# ---- async parity --------------------------------------------------------


def test_async_operational_surface_mirrors_the_sync_wire(server, async_client):
    def respond(request):
        path = request.url.path
        if path.endswith("/bulk"):
            return json_response(
                200,
                {"created": 1, "failed": 0, "results": [{"index": 0, "id": "t1", "status": "queued"}]},
            )
        if path.endswith("/check"):
            return json_response(200, {"issues": [], "eligibleWorkerCount": 1, "uncoveredTags": [], "evaluatedAt": 1})
        if path.endswith("/escalate"):
            return json_response(200, {"id": "t1", "escalated": True, "parked": False, "escalationLevel": 1})
        if path.endswith("/parked") or path.endswith("/scheduled"):
            return json_response(200, {"tasks": [TASK], "count": 1})
        if path.endswith("/stats/sla"):
            return json_response(200, {"tag": None, "offers": 1})
        if path.endswith("/queue-audit"):
            return json_response(200, {"evaluatedAt": 1, "scanned": 0, "entries": [], "sweepBacklog": {}})
        if path.endswith("/v1/portal"):
            return json_response(200, {"portalEnabled": True, "portalUrl": "https://x/", "exists": True})
        if path.endswith("/weights/revert"):
            return json_response(200, {"reverted": [], "count": 0})
        if path.startswith("/v1/skills/"):
            return json_response(200, {"id": "sk_1", "key": "welsh", "name": "Welsh"})
        return json_response(200, {"id": "t1", "status": "accepted"})

    server.set_responder(respond)

    async def main():
        try:
            return (
                await async_client.tasks.create_many([CreateTask(tags=["a"])]),
                await async_client.tasks.check(CreateTask(tags=["a"])),
                await async_client.tasks.ack("t1", "agent_1"),
                await async_client.tasks.escalate("t1"),
                await async_client.tasks.parked(),
                await async_client.tasks.scheduled(),
                await async_client.tasks.unpark("t1", UnparkTask(reset_sla=True)),
                await async_client.sla_stats(),
                await async_client.queue_audit(QueueAuditQuery(limit=5)),
                await async_client.portal(),
                await async_client.learning.revert_weights(["agent_1"]),
                await async_client.skills.get("sk_1"),
            )
        finally:
            await async_client.aclose()

    bulk, check, ack, escalate, parked, scheduled, unpark, sla, audit, portal, revert, skill = asyncio.run(main())

    assert bulk.created == 1
    assert check.eligible_worker_count == 1
    assert ack.status == "accepted"
    assert escalate.escalation_level == 1
    assert parked.count == 1 and scheduled.count == 1
    assert unpark.status == "accepted"
    assert sla.offers == 1
    assert audit.scanned == 0
    assert portal.portal_enabled is True
    assert revert.count == 0
    assert skill.key == "welsh"
