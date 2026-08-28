"""How the SDK turns method arguments into HTTP requests.

These drive the request builders directly rather than through a client. They are the cheapest
place to pin down the rules that matter most and are easiest to get subtly wrong:

  * optional fields must be *absent*, never ``null`` — every partial-update endpoint keeps the
    stored value for an absent field, so a stray null silently wipes data;
  * ids go into paths percent-encoded, so an id containing ``/`` cannot escape its path segment;
  * a few parameters have bespoke wire rules (comma-joined ``selected``, conditional
    ``releaseBacklog``, redundant ``workflowId``).
"""

from __future__ import annotations

import pytest

from fivexer import (
    AddComment,
    CreateAttachment,
    CreateNotificationChannel,
    CreateNotificationSequence,
    CreateTask,
    LearningFeedbackItem,
    ListRunsQuery,
    ListTasksQuery,
    NotificationSequenceStep,
    PatchSkill,
    PatchWorker,
    RequiredSkill,
    SetTaskContext,
    StartRun,
    StatsWindowQuery,
    SuggestWorkers,
    UpdateNotificationChannel,
    UpdateNotificationSequence,
    UpsertSkill,
    UpsertWorker,
    WorkerLogin,
    WorkflowDefinitionInput,
    WorkflowRouting,
    WorkflowStep,
)
from fivexer import _specs as specs

# ---- absent vs null -------------------------------------------------------


def test_creating_a_task_sends_only_the_fields_that_were_set():
    spec = specs.tasks_create(CreateTask(tags=["english"]))

    assert spec.method == "POST"
    assert spec.path == "/tasks"
    assert spec.body == {"tags": ["english"]}


def test_creating_a_task_carries_geo_cidr_and_rich_data_together():
    spec = specs.tasks_create(
        CreateTask(
            tags=["field"],
            priority=90,
            title="Fix the meter",
            description="Meter 41 is stuck",
            context={"meterId": "41"},
            latitude=59.4,
            longitude=24.7,
            max_distance_km=25,
            require_geo=True,
            allowed_cidrs=["10.0.0.0/8"],
            skill_thresholds={"electrical": 3},
            vetoed_workers=["agent_9"],
            meta={"ticket": "T-1"},
        )
    )

    assert spec.body == {
        "tags": ["field"],
        "priority": 90,
        "title": "Fix the meter",
        "description": "Meter 41 is stuck",
        "context": {"meterId": "41"},
        "latitude": 59.4,
        "longitude": 24.7,
        "maxDistanceKm": 25,
        "requireGeo": True,
        "allowedCidrs": ["10.0.0.0/8"],
        "skillThresholds": {"electrical": 3},
        "vetoedWorkers": ["agent_9"],
        "meta": {"ticket": "T-1"},
    }


def test_an_empty_worker_patch_sends_an_empty_body_rather_than_nulls():
    # A body of nulls would clear tags, weights and skills instead of leaving them alone.
    assert specs.workers_patch("agent_1", PatchWorker()).body == {}


def test_patching_one_worker_field_leaves_the_others_absent():
    spec = specs.workers_patch("agent_1", PatchWorker(max_backlog_size=3))

    assert spec.method == "PATCH"
    assert spec.body == {"maxBacklogSize": 3}


def test_completing_a_task_without_a_result_omits_the_result_key():
    assert specs.tasks_complete("t_1", "agent_1").body == {"workerId": "agent_1"}


def test_completing_a_task_with_a_result_includes_it():
    spec = specs.tasks_complete("t_1", "agent_1", {"refunded": True})

    assert spec.body == {"workerId": "agent_1", "result": {"refunded": True}}


def test_assigning_without_force_omits_the_flag():
    assert specs.tasks_assign("t_1", "agent_1").body == {"workerId": "agent_1"}


def test_forcing_an_assignment_sends_the_override_flag():
    assert specs.tasks_assign("t_1", "agent_1", force=True).body == {
        "workerId": "agent_1",
        "force": True,
    }


def test_an_empty_context_update_sends_an_empty_body():
    assert specs.context_set("t_1", SetTaskContext()).body == {}


def test_setting_context_serialises_references_without_ids():
    from fivexer import TaskReferenceInput

    spec = specs.context_set(
        "t_1",
        SetTaskContext(
            title="Refund",
            references=[TaskReferenceInput(url="https://crm/o/41", label="Order 41")],
        ),
    )

    assert spec.method == "PUT"
    assert spec.body == {
        "title": "Refund",
        "references": [{"url": "https://crm/o/41", "label": "Order 41"}],
    }


def test_a_comment_without_attribution_sends_only_the_body():
    assert specs.comments_add("t_1", AddComment(body="Called back")).body == {"body": "Called back"}


def test_a_comment_attributed_to_a_worker_carries_the_worker_id():
    spec = specs.comments_add("t_1", AddComment(body="Called back", worker_id="agent_1"))

    assert spec.body == {"body": "Called back", "workerId": "agent_1"}


def test_an_empty_notification_channel_update_sends_an_empty_body():
    assert specs.channels_update("ch_1", UpdateNotificationChannel()).body == {}


def test_disabling_a_channel_touches_only_the_disabled_flag():
    assert specs.channels_update("ch_1", UpdateNotificationChannel(disabled=True)).body == {"disabled": True}


def test_an_empty_sequence_update_sends_an_empty_body():
    assert specs.sequences_update("seq_1", UpdateNotificationSequence()).body == {}


def test_updating_sequence_steps_serialises_each_step():
    spec = specs.sequences_update(
        "seq_1",
        UpdateNotificationSequence(steps=[NotificationSequenceStep(trigger="matched", event_type="task.expiring")]),
    )

    assert spec.body == {"steps": [{"trigger": "matched", "eventType": "task.expiring"}]}


def test_a_sequence_step_offset_is_sent_only_when_given():
    with_offset = NotificationSequenceStep(trigger="matched", event_type="e", offset_ms=1000)
    without = NotificationSequenceStep(trigger="matched", event_type="e")

    assert with_offset.to_json() == {"trigger": "matched", "eventType": "e", "offsetMs": 1000}
    assert without.to_json() == {"trigger": "matched", "eventType": "e"}


def test_an_empty_skill_patch_sends_an_empty_body():
    assert specs.skills_patch("skl_1", PatchSkill()).body == {}


def test_a_skill_without_a_description_omits_it():
    assert specs.skills_create(UpsertSkill(key="refunds", name="Refunds")).body == {
        "key": "refunds",
        "name": "Refunds",
    }


def test_starting_a_run_without_context_sends_an_empty_body():
    assert specs.workflows_run("wf_1", None).body == {}


def test_starting_a_run_carries_context_and_initiator():
    spec = specs.workflows_run("wf_1", StartRun(context={"a": 1}, initiator_worker_id="agent_1"))

    assert spec.body == {"context": {"a": 1}, "initiatorWorkerId": "agent_1"}


def test_completing_a_callback_step_without_data_sends_an_empty_body():
    assert specs.runs_complete_step("run_1", "review", None).body == {}


def test_failing_a_callback_step_carries_the_error_message():
    spec = specs.runs_fail_step("run_1", "review", "compliance unreachable")

    assert spec.path == "/workflow-runs/run_1/steps/review/fail"
    assert spec.body == {"error": "compliance unreachable"}


def test_applying_learned_weights_to_every_worker_sends_an_empty_body():
    assert specs.learning_apply_weights(None).body == {}


def test_applying_learned_weights_to_named_workers_lists_them():
    assert specs.learning_apply_weights(["agent_1"]).body == {"workerIds": ["agent_1"]}


def test_bulk_feedback_serialises_signals_and_rewards_independently():
    spec = specs.learning_feedback_bulk(
        [
            LearningFeedbackItem(task_id="t_1", reward=1.0),
            LearningFeedbackItem(task_id="t_2", signals={"csat": 0.9}),
        ]
    )

    assert spec.body == {"items": [{"taskId": "t_1", "reward": 1.0}, {"taskId": "t_2", "signals": {"csat": 0.9}}]}


def test_starting_a_break_without_a_reason_sends_an_empty_body():
    assert specs.worker_start_break(None).body == {}


def test_starting_a_break_with_a_reason_sends_it():
    assert specs.worker_start_break("lunch").body == {"reason": "lunch"}


def test_an_upsert_with_no_worker_object_sends_an_empty_body():
    assert specs.workers_upsert(None).body == {}


def test_upserting_a_worker_serialises_skill_assignments():
    from fivexer import WorkerSkillAssignment

    spec = specs.workers_upsert(
        UpsertWorker(
            id="agent_1",
            tags=["english"],
            skills=[WorkerSkillAssignment(skill_id="skl_1", level=4)],
            max_backlog_size=5,
        )
    )

    assert spec.body == {
        "id": "agent_1",
        "tags": ["english"],
        "skills": [{"skillId": "skl_1", "level": 4}],
        "maxBacklogSize": 5,
    }


def test_a_skill_weight_override_is_sent_only_when_set():
    from fivexer import WorkerSkillAssignment

    assert WorkerSkillAssignment(skill_id="s", level=3, weight_override=80).to_json() == {
        "skillId": "s",
        "level": 3,
        "weightOverride": 80,
    }


def test_an_attachment_without_worker_attribution_omits_the_worker_id():
    spec = specs.attachments_create(
        "t_1", CreateAttachment(filename="a.pdf", content_type="application/pdf", size_bytes=10)
    )

    assert spec.body == {"filename": "a.pdf", "contentType": "application/pdf", "sizeBytes": 10}


def test_suggesting_workers_serialises_required_skill_levels():
    spec = specs.tasks_suggest_workers(
        SuggestWorkers(tags=["english"], required_skills=[RequiredSkill(skill_id="skl_1", min_level=3)])
    )

    assert spec.body == {"tags": ["english"], "requiredSkills": [{"skillId": "skl_1", "minLevel": 3}]}


# ---- bespoke wire rules ---------------------------------------------------


def test_pausing_a_worker_does_not_mention_backlog_release():
    # `releaseBacklog: false` is rejected on resume, so the SDK never sends the key unset.
    assert specs.workers_set_availability("agent_1", False).body == {"available": False}


def test_pausing_and_releasing_the_backlog_sets_the_flag():
    assert specs.workers_set_availability("agent_1", False, True).body == {
        "available": False,
        "releaseBacklog": True,
    }


def test_resuming_a_worker_sends_only_availability():
    assert specs.workers_set_availability("agent_1", True).body == {"available": True}


def test_suggesting_skills_joins_the_selected_list_into_one_parameter():
    spec = specs.skills_suggest(["refunds", "billing"], limit=5)

    assert spec.query == {"selected": "refunds,billing", "limit": "5"}


def test_suggesting_skills_with_nothing_selected_sends_no_selected_parameter():
    assert specs.skills_suggest([], None).query == {}
    assert specs.skills_suggest(None, None).query == {}


def test_listing_one_definitions_runs_drops_the_redundant_workflow_filter():
    # The workflow id is already in the path; repeating it as a filter would be noise.
    spec = specs.workflows_list_runs("wf_1", ListRunsQuery(workflow_id="wf_other", status="active"))

    assert spec.path == "/workflows/wf_1/runs"
    assert spec.query == {"status": "active"}


def test_listing_all_runs_keeps_the_workflow_filter():
    spec = specs.runs_list(ListRunsQuery(workflow_id="wf_1", limit=10))

    assert spec.path == "/workflow-runs"
    assert spec.query == {"workflowId": "wf_1", "limit": "10"}


def test_an_unfiltered_task_list_sends_no_query_parameters():
    assert specs.tasks_list(None).query == {}


def test_a_filtered_task_list_stringifies_every_parameter():
    assert specs.tasks_list(ListTasksQuery(status="queued", cursor="c1", limit=50)).query == {
        "status": "queued",
        "cursor": "c1",
        "limit": "50",
    }


def test_a_stats_window_maps_from_onto_the_reserved_word_safe_field():
    # `from` is a Python keyword, so the model spells it `from_` but the wire must say `from`.
    assert specs.stats_timeseries(StatsWindowQuery(from_="2026-07-23T00:00:00Z", bucket="day")).query == {
        "from": "2026-07-23T00:00:00Z",
        "bucket": "day",
    }


def test_break_metrics_default_to_the_whole_window():
    assert specs.breaks_metrics().query == {}


# ---- path encoding --------------------------------------------------------


@pytest.mark.parametrize(
    ("build", "expected"),
    [
        (lambda: specs.tasks_get("a/b"), "/tasks/a%2Fb"),
        (lambda: specs.comments_remove("t 1", "c#1"), "/tasks/t%201/comments/c%231"),
        (lambda: specs.attachments_download("t/1", "a/2"), "/tasks/t%2F1/attachments/a%2F2/download"),
        (lambda: specs.workers_queue("agent 1"), "/workers/agent%201/queue"),
        (lambda: specs.skills_remove("a/b"), "/skills/a%2Fb"),
        (lambda: specs.workflows_get("wf/1"), "/workflows/wf%2F1"),
        (lambda: specs.runs_complete_step("r/1", "s/1"), "/workflow-runs/r%2F1/steps/s%2F1/complete"),
        (lambda: specs.learning_worker_stats("a/b"), "/learning/workers/a%2Fb"),
        (lambda: specs.sequences_get("s/1"), "/notification-sequences/s%2F1"),
        (lambda: specs.channels_get("c/1"), "/notification-channels/c%2F1"),
        (lambda: specs.worker_task_detail("t/1"), "/portal/tasks/t%2F1"),
        (lambda: specs.worker_queue("a/1"), "/portal/workers/a%2F1/queue"),
        (lambda: specs.teams_get("t/1"), "/teams/t%2F1"),
        (lambda: specs.teams_set_members("t/1", []), "/teams/t%2F1/members"),
        (lambda: specs.join_links_revoke("jl/1"), "/join-links/jl%2F1"),
        (lambda: specs.identities_remove("a/1"), "/workers/a%2F1/identity"),
        (lambda: specs.identities_resend_invite("a/1"), "/worker-identities/a%2F1/invite/resend"),
        (lambda: specs.skills_get("s/1"), "/skills/s%2F1"),
        (lambda: specs.tasks_unpark("t/1"), "/tasks/t%2F1/unpark"),
        (lambda: specs.tasks_escalate("t/1"), "/tasks/t%2F1/escalate"),
        (lambda: specs.tasks_ack("t/1", "a1"), "/tasks/t%2F1/ack"),
        (lambda: specs.worker_comments_add("t/1", "hi"), "/portal/tasks/t%2F1/comments"),
    ],
)
def test_ids_cannot_escape_their_path_segment(build, expected):
    assert build().path == expected


# ---- input type guard -----------------------------------------------------


@pytest.mark.parametrize(
    "build",
    [
        lambda: specs.tasks_create({"tags": ["x"]}),
        lambda: specs.workers_upsert({"id": "w"}),
        lambda: specs.workers_patch("w", {"tags": []}),
        lambda: specs.skills_create({"key": "k", "name": "n"}),
        lambda: specs.context_set("t", {"title": "x"}),
        lambda: specs.comments_add("t", {"body": "x"}),
        lambda: specs.attachments_create("t", {"filename": "f"}),
        lambda: specs.workflows_save("wf", {"name": "n", "steps": []}),
        lambda: specs.sequences_create({"name": "n", "steps": []}),
        lambda: specs.channels_create({"type": "webhook"}),
        lambda: specs.worker_login({"workspaceId": "ws"}),
        lambda: specs.tasks_suggest_workers({"tags": []}),
        lambda: specs.learning_feedback_bulk([{"taskId": "t"}]),
    ],
)
def test_passing_a_raw_dict_instead_of_a_model_is_rejected_immediately(build):
    with pytest.raises(TypeError):
        build()


def test_the_type_error_names_both_the_expected_and_the_supplied_type():
    with pytest.raises(TypeError, match="expected CreateTask, got dict"):
        specs.tasks_create({"tags": ["x"]})


# ---- workflow definitions -------------------------------------------------


def test_a_terminal_step_serialises_an_explicit_null_next_step():
    # `defaultNextStepId: null` is what ends a workflow — it must survive None-pruning.
    step = WorkflowStep(id="done", name="Done", task_type="assignment", end_workflow=True)

    assert step.to_json() == {
        "id": "done",
        "name": "Done",
        "taskType": "assignment",
        "defaultNextStepId": None,
    }


def test_a_step_with_no_declared_successor_omits_the_key_entirely():
    assert WorkflowStep(id="a", name="A").to_json() == {"id": "a", "name": "A"}


def test_saving_a_definition_serialises_routing_rules_in_order():
    spec = specs.workflows_save(
        "wf_1",
        WorkflowDefinitionInput(
            name="Onboarding",
            initial_step_id="collect",
            steps=[
                WorkflowStep(
                    id="collect",
                    name="Collect",
                    task_type="assignment",
                    routing=[
                        WorkflowRouting(condition="result.ok === true", target_step_id="review"),
                        WorkflowRouting(condition="true", target_step_id="reject"),
                    ],
                )
            ],
        ),
    )

    assert spec.method == "PUT"
    assert spec.body["steps"][0]["routing"] == [
        {"condition": "result.ok === true", "targetStepId": "review"},
        {"condition": "true", "targetStepId": "reject"},
    ]


def test_saving_a_definition_never_puts_the_id_in_the_body():
    # The URL is the id of record; a body id would be ambiguous.
    spec = specs.workflows_save("wf_1", WorkflowDefinitionInput(name="N", steps=[]))

    assert "id" not in spec.body
    assert spec.path == "/workflows/wf_1"


def test_a_login_always_sends_all_three_credentials():
    spec = specs.worker_login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

    assert spec.body == {"workspaceId": "ws_1", "workerId": "agent_1", "pin": "4821"}


def test_creating_a_channel_without_a_secret_omits_it():
    spec = specs.channels_create(
        CreateNotificationChannel(type="webhook", target="https://hooks", events=["task.matched"])
    )

    assert spec.body == {"type": "webhook", "target": "https://hooks", "events": ["task.matched"]}


def test_creating_a_sequence_without_options_sends_name_and_steps_only():
    spec = specs.sequences_create(CreateNotificationSequence(name="Escalate", steps=[]))

    assert spec.body == {"name": "Escalate", "steps": []}
