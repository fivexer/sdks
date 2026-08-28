"""Task policies on the way in and on the way out.

Four policies now reach `tasks.create`: escalation (the response clock), SLA (the completion
clock, shelf life and rejection budget), schedule (when a task may be offered at all) and
recurrence (a standing template). They are separate objects because they answer separate
questions and are stored separately — collapsing them into one "deadlines" bag would make
"inherit the workspace default" impossible to express per policy.

The distinction these tests exist to protect is *absent* versus *null*. Omitting a policy
inherits the workspace default; sending an explicit null opts the task out of it. Those are
different wire values with different behaviour, so `NULL_POLICY` must survive the SDK's
omit-nulls pass that every other unset field is removed by.
"""

from __future__ import annotations

from fivexer import (
    NULL_POLICY,
    CreateTask,
    EscalationPolicy,
    RecurrencePolicy,
    RequiredSkill,
    SchedulePolicy,
    SlaPolicy,
)
from tests.conftest import json_response, read_body


def test_create_sends_every_policy_in_its_own_wire_object(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(
        CreateTask(
            tags=["billing"],
            escalation=EscalationPolicy(
                respond_within_ms=60_000,
                on_no_response="block",
                priority_boost=10,
                tiers=[["billing"], ["billing", "english"]],
                max_escalations=2,
                on_exhausted="park",
            ),
            sla=SlaPolicy(
                complete_within_ms=3_600_000,
                expire_after_ms=86_400_000,
                max_rejections=3,
                on_completion_breach="notify",
                on_max_rejections="park",
                on_expire="drop",
            ),
            schedule=SchedulePolicy(not_before=1_756_000_000_000, not_after=1_756_003_600_000, on_miss="park"),
        )
    )

    body = read_body(server.last)
    assert body["escalation"] == {
        "onNoResponse": "block",
        "priorityBoost": 10,
        "tiers": [["billing"], ["billing", "english"]],
        "maxEscalations": 2,
        "onExhausted": "park",
        "respondWithinMs": 60_000,
    }
    assert body["sla"] == {
        "completeWithinMs": 3_600_000,
        "expireAfterMs": 86_400_000,
        "maxRejections": 3,
        "onCompletionBreach": "notify",
        "onMaxRejections": "park",
        "onExpire": "drop",
    }
    assert body["schedule"] == {
        "notBefore": 1_756_000_000_000,
        "notAfter": 1_756_003_600_000,
        "onMiss": "park",
    }


def test_unset_policy_fields_are_omitted_not_sent_as_null(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["billing"], escalation=EscalationPolicy(respond_within_ms=30_000)))

    # A null inside a policy object would read as "clear this setting", not "I did not set it".
    assert read_body(server.last)["escalation"] == {"respondWithinMs": 30_000}


def test_omitting_a_policy_leaves_it_off_the_body_entirely(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["billing"]))

    body = read_body(server.last)
    # Absent means "inherit the workspace default" — the SDK must not decide that for the caller.
    assert "escalation" not in body
    assert "sla" not in body


def test_null_policy_sends_an_explicit_null_to_opt_out_of_the_default(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["billing"], escalation=NULL_POLICY, sla=NULL_POLICY))

    body = read_body(server.last)
    # The one case where a null must survive: it is how a task opts out of a workspace default.
    assert body["escalation"] is None
    assert body["sla"] is None
    assert "escalation" in body and "sla" in body


def test_create_sends_required_skills_team_gate_and_preference(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(
        CreateTask(
            tags=["billing"],
            required_skills=[RequiredSkill(skill_id="sk_node", min_level=3)],
            team_id="team_1",
        )
    )

    body = read_body(server.last)
    assert body["requiredSkills"] == [{"skillId": "sk_node", "minLevel": 3}]
    assert body["teamId"] == "team_1"


def test_prefer_team_is_a_separate_field_from_the_hard_gate(server, client):
    server.set_response(json_response(202, {"id": "task_1", "status": "queued"}))

    client.tasks.create(CreateTask(tags=["billing"], prefer_team_id="team_1"))

    body = read_body(server.last)
    # A soft preference must never be sent as the hard gate: teamId excludes everyone else.
    assert body["preferTeamId"] == "team_1"
    assert "teamId" not in body


def test_task_reads_back_its_policies_and_ladder_position(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_1",
                "tags": ["billing"],
                "priority": 90,
                "status": "queued",
                "workerId": None,
                "createdAt": 1_756_000_000_000,
                "skillThresholds": {"billing": 3},
                "escalation": {"respondWithinMs": 60_000, "onNoResponse": "block"},
                "escalationLevel": 2,
                "sla": {"completeWithinMs": 3_600_000},
                "schedule": {"notBefore": 1_756_000_000_000},
            },
        )
    )

    task = client.tasks.get("task_1")

    assert task.escalation is not None and task.escalation.respond_within_ms == 60_000
    assert task.escalation.on_no_response == "block"
    # Without escalation_level a client can render the ladder but not where the task sits on it.
    assert task.escalation_level == 2
    assert task.sla is not None and task.sla.complete_within_ms == 3_600_000
    assert task.schedule is not None and task.schedule.not_before == 1_756_000_000_000
    # The gate a queued task is waiting on — the answer to "why is this still queued?"
    assert task.skill_thresholds == {"billing": 3}


def test_a_task_without_policies_reads_them_as_none_rather_than_empty_objects(server, client):
    server.set_response(
        json_response(
            200,
            {
                "id": "task_1",
                "tags": ["billing"],
                "priority": None,
                "status": "queued",
                "workerId": None,
                "createdAt": 1,
                "escalation": None,
                "sla": None,
            },
        )
    )

    task = client.tasks.get("task_1")

    # An empty SlaPolicy() would read as "an SLA with no deadlines", which is a different fact.
    assert task.escalation is None
    assert task.sla is None
    assert task.escalation_level is None


def test_recurrence_makes_a_template_and_keeps_every_ms_required(server, client):
    server.set_response(json_response(202, {"id": "nightly", "status": "recurring"}))

    result = client.tasks.create(
        CreateTask(
            tags=["ops"],
            id="nightly",
            recurrence=RecurrencePolicy(
                every_ms=86_400_000,
                start_at=1_756_000_000_000,
                window_ms=3_600_000,
                on_miss="park",
                until=1_788_000_000_000,
                max_occurrences=30,
                catch_up="all",
            ),
        )
    )

    assert result.status == "recurring"
    assert read_body(server.last)["recurrence"] == {
        "startAt": 1_756_000_000_000,
        "windowMs": 3_600_000,
        "onMiss": "park",
        "until": 1_788_000_000_000,
        "maxOccurrences": 30,
        "catchUp": "all",
        "everyMs": 86_400_000,
    }


def test_a_minimal_recurrence_sends_only_the_interval(server, client):
    server.set_response(json_response(202, {"id": "nightly", "status": "recurring"}))

    client.tasks.create(CreateTask(tags=["ops"], recurrence=RecurrencePolicy(every_ms=60_000)))

    # catch_up defaults to 'skip' server-side; echoing it would claim a choice nobody made.
    assert read_body(server.last)["recurrence"] == {"everyMs": 60_000}
