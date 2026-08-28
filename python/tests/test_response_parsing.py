"""How the SDK reads API responses back into models.

Parsing is where a wrong assumption costs the caller silently: a nullable field read as a
default, an optional list flattened to empty, a nested object dropped. Each test here pins a
distinction the API actually makes.
"""

from __future__ import annotations

from fivexer import (
    Attachment,
    Comment,
    LearningStatus,
    QuotaInfo,
    Skill,
    Task,
    TaskContext,
    TeamPresence,
    WorkerBreakToday,
    WorkerDetail,
    WorkflowRun,
    WorkflowStep,
    WorkspaceStats,
)

# ---- nullable vs missing --------------------------------------------------


def test_a_task_read_from_a_list_has_no_rich_data_summary():
    # `data` is populated on single reads only; a list entry must not fake an empty summary.
    task = Task.from_json({"id": "t_1", "tags": [], "priority": None, "status": "queued"})

    assert task.data is None
    assert task.title is None
    assert task.archived is False


def test_a_single_task_read_exposes_its_rich_data_counts():
    task = Task.from_json(
        {
            "id": "t_1",
            "tags": ["english"],
            "status": "pending",
            "title": "Refund",
            "data": {"hasContext": True, "referenceCount": 2, "attachmentCount": 1, "commentCount": 3},
        }
    )

    assert task.data is not None
    assert (task.data.has_context, task.data.reference_count, task.data.comment_count) == (True, 2, 3)


def test_a_workflow_step_task_reports_the_run_and_step_it_belongs_to():
    task = Task.from_json(
        {"id": "t_1", "tags": [], "status": "pending", "workflowRunId": "run_1", "workflowStepId": "collect"}
    )

    assert (task.workflow_run_id, task.workflow_step_id) == ("run_1", "collect")


def test_an_archived_task_carries_its_stored_result():
    task = Task.from_json(
        {"id": "t_1", "tags": [], "status": "completed", "archived": True, "result": {"refunded": True}}
    )

    assert task.archived is True
    assert task.result == {"refunded": True}


def test_a_worker_with_no_skills_configured_is_distinguishable_from_one_with_none_assigned():
    # null means "skills not in use for this worker"; [] means "configured, currently empty".
    unset = WorkerDetail.from_json({"id": "a", "skills": None})
    empty = WorkerDetail.from_json({"id": "a", "skills": []})

    assert unset.skills is None
    assert empty.skills == []


def test_a_worker_detail_reports_its_effective_skill_weights():
    detail = WorkerDetail.from_json(
        {
            "id": "agent_1",
            "tags": ["english"],
            "routingWeights": {"english": 100},
            "skills": [{"skillId": "skl_1", "key": "refunds", "name": "Refunds", "level": 4, "weight": 80}],
            "maxBacklogSize": 5,
            "available": False,
            "queueDepth": 2,
        }
    )

    assert detail.skills is not None
    assert detail.skills[0].weight == 80
    assert detail.skills[0].weight_override is None
    assert (detail.available, detail.queue_depth, detail.max_backlog_size) == (False, 2, 5)


def test_a_worker_with_no_backlog_cap_reports_none_rather_than_zero():
    # Zero is a real setting meaning "receive nothing" — it must not collide with "unset".
    assert WorkerDetail.from_json({"id": "a", "maxBacklogSize": None}).max_backlog_size is None
    assert WorkerDetail.from_json({"id": "a", "maxBacklogSize": 0}).max_backlog_size == 0


def test_learning_status_before_any_reward_has_no_statistics():
    status = LearningStatus.from_json(
        {"enabled": False, "shadowMode": True, "autoWeights": False, "stats": None, "modelSize": 0}
    )

    assert status.stats is None
    assert status.shadow_mode is True


def test_learning_status_exposes_reward_averages_once_rewards_exist():
    status = LearningStatus.from_json(
        {
            "enabled": True,
            "shadowMode": False,
            "autoWeights": True,
            "stats": {"decisions": 1200, "rewards": 800, "totalReward": 940.5, "averageReward": 1.175},
            "modelSize": 64,
        }
    )

    assert status.stats is not None
    assert status.stats.average_reward == 1.175
    assert status.stats.decisions == 1200


def test_a_finished_run_has_no_current_step_but_keeps_its_history():
    run = WorkflowRun.from_json(
        {
            "id": "run_1",
            "workflowId": "wf_1",
            "status": "completed",
            "currentStepId": None,
            "currentTaskId": None,
            "initiatorWorkerId": "agent_1",
            "context": {},
            "definitionVersion": 2,
            "history": [
                {
                    "stepId": "collect",
                    "taskId": "t_1",
                    "workerId": "agent_1",
                    "completedAt": 1750000500000,
                    "result": {"ok": True},
                }
            ],
            "parallelBranches": None,
            "createdAt": 1,
            "updatedAt": 2,
        }
    )

    assert run.current_step_id is None
    assert run.parallel_branches is None
    assert run.history[0].result == {"ok": True}


def test_a_run_with_parallel_branches_lists_each_branch():
    run = WorkflowRun.from_json(
        {
            "id": "run_1",
            "workflowId": "wf_1",
            "status": "active",
            "initiatorWorkerId": "agent_1",
            "definitionVersion": 1,
            "createdAt": 1,
            "updatedAt": 2,
            "parallelBranches": [
                {"stepId": "a", "assignmentId": "t_1", "status": "pending"},
                {"stepId": "b", "assignmentId": "t_2", "status": "completed", "result": {"ok": 1}},
            ],
        }
    )

    assert run.parallel_branches is not None
    assert [b.step_id for b in run.parallel_branches] == ["a", "b"]
    assert run.parallel_branches[1].result == {"ok": 1}


def test_an_empty_queue_reports_no_oldest_wait_rather_than_zero():
    # Zero would read as "a task has been waiting 0ms", which is a different fact.
    stats = WorkspaceStats.from_json(
        {
            "plan": "free",
            "tasks": {},
            "workers": 0,
            "queue": {"oldestWaitingMs": None, "perWorker": []},
            "meter": {"period": "2026-07", "matchedTasks": 0, "includedTasksPerMonth": 1000},
            "matching": {"fairness": "first-come", "maxTasksPerWindow": None, "windowMs": None},
        }
    )

    assert stats.queue.oldest_waiting_ms is None
    assert stats.matching.fairness == "first-come"
    assert stats.matching.max_tasks_per_window is None


def test_workspace_stats_report_per_worker_load_and_the_live_balancer_policy():
    stats = WorkspaceStats.from_json(
        {
            "plan": "pro",
            "tasks": {"queued": 3, "pending": 1},
            "workers": 2,
            "queue": {
                "oldestWaitingMs": 84000,
                "perWorker": [
                    {"workerId": "agent_1", "backlog": 2, "maxBacklogSize": 5, "available": True},
                    {"workerId": "agent_2", "backlog": 0, "maxBacklogSize": 5, "available": False},
                ],
            },
            "meter": {"period": "2026-07", "matchedTasks": 421, "includedTasksPerMonth": 50000},
            "matching": {"fairness": "balanced", "maxTasksPerWindow": 20, "windowMs": 3600000},
        }
    )

    assert stats.queue.oldest_waiting_ms == 84000
    assert [w.worker_id for w in stats.queue.per_worker] == ["agent_1", "agent_2"]
    assert stats.queue.per_worker[1].available is False
    assert (stats.matching.fairness, stats.matching.max_tasks_per_window) == ("balanced", 20)
    assert stats.meter_matched_tasks == 421


def test_stats_from_a_data_plane_only_deployment_still_parse():
    # queue/matching are absent when the control plane is not attached; defaults must hold.
    stats = WorkspaceStats.from_json({"plan": "free", "tasks": {}, "workers": 0, "meter": {}})

    assert stats.queue.per_worker == []
    assert stats.matching.fairness == "first-come"
    assert stats.meter_period == ""


def test_a_pending_attachment_has_no_confirmation_time():
    attachment = Attachment.from_json(
        {
            "id": "att_1",
            "taskId": "t_1",
            "filename": "a.pdf",
            "contentType": "application/pdf",
            "sizeBytes": 10,
            "status": "pending",
            "uploader": {"type": "api", "id": None},
            "createdAt": 1,
            "confirmedAt": None,
        }
    )

    assert attachment.confirmed_at is None
    assert attachment.status == "pending"
    assert attachment.uploader.type == "api"


def test_a_comment_from_the_integration_has_no_author_identity():
    comment = Comment.from_json(
        {
            "id": "c_1",
            "taskId": "t_1",
            "author": {"type": "api", "id": None, "label": None},
            "body": "Escalated",
            "createdAt": 1,
        }
    )

    assert (comment.author.type, comment.author.id, comment.author.label) == ("api", None, None)


def test_a_comment_on_behalf_of_a_worker_carries_their_label():
    comment = Comment.from_json(
        {
            "id": "c_1",
            "taskId": "t_1",
            "author": {"type": "worker", "id": "agent_1", "label": "Ada"},
            "body": "Called back",
            "createdAt": 1,
        }
    )

    assert (comment.author.type, comment.author.label) == ("worker", "Ada")


def test_task_context_with_nothing_stored_reads_as_empty_not_missing():
    context = TaskContext.from_json(
        {"taskId": "t_1", "title": None, "description": None, "context": None, "references": []}
    )

    assert context.references == []
    assert context.title is None


def test_a_skill_without_a_description_reads_as_none():
    assert Skill.from_json({"id": "s", "key": "k", "name": "n", "description": None}).description is None


def test_team_presence_counts_each_availability_state():
    presence = TeamPresence.from_json(
        {
            "workers": [
                {"workerId": "agent_1", "label": "Ada", "status": "working"},
                {
                    "workerId": "agent_2",
                    "label": "Grace",
                    "status": "on-break",
                    "breakStartedAt": "2026-07-24T11:30:00Z",
                    "breakReason": "lunch",
                },
                {"workerId": "agent_3", "label": "Alan", "status": "paused"},
            ],
            "counts": {"working": 1, "onBreak": 1, "paused": 1, "total": 3},
        }
    )

    assert presence.counts.on_break == 1
    assert presence.counts.total == 3
    assert presence.workers[1].break_reason == "lunch"
    assert presence.workers[0].break_started_at is None


def test_a_worker_not_currently_on_break_has_no_active_entry():
    today = WorkerBreakToday.from_json(
        {
            "workerId": "agent_1",
            "since": "2026-07-24T00:00:00Z",
            "breaks": [
                {
                    "id": "brk_1",
                    "startedAt": "2026-07-24T11:30:00Z",
                    "endedAt": "2026-07-24T12:00:00Z",
                    "reason": "lunch",
                    "durationMs": 1800000,
                }
            ],
            "active": None,
            "completedTasks": 7,
            "totalBreakMs": 1800000,
        }
    )

    assert today.active is None
    assert today.breaks[0].ended_at == "2026-07-24T12:00:00Z"
    assert today.completed_tasks == 7


def test_an_open_break_appears_as_the_active_entry():
    today = WorkerBreakToday.from_json(
        {
            "workerId": "agent_1",
            "since": "2026-07-24T00:00:00Z",
            "breaks": [],
            "active": {"id": "brk_2", "startedAt": "2026-07-24T13:00:00Z", "endedAt": None, "durationMs": 0},
        }
    )

    assert today.active is not None
    assert today.active.ended_at is None


def test_a_terminal_step_read_back_is_distinguishable_from_one_with_no_successor():
    terminal = WorkflowStep.from_json({"id": "a", "name": "A", "defaultNextStepId": None})
    undeclared = WorkflowStep.from_json({"id": "a", "name": "A"})

    assert terminal.end_workflow is True
    assert undeclared.end_workflow is False
    # …and re-serialising each preserves the distinction.
    assert terminal.to_json()["defaultNextStepId"] is None
    assert "defaultNextStepId" not in undeclared.to_json()


def test_a_step_round_trips_its_routing_and_retry_policy():
    step = WorkflowStep.from_json(
        {
            "id": "review",
            "name": "Review",
            "taskType": "external",
            "external": {"name": "compliance"},
            "routing": [{"condition": "result.ok", "targetStepId": "done"}],
            "failurePolicy": "retry",
            "maxRetries": 3,
            "timeoutMs": 300000,
        }
    )

    assert step.routing is not None
    assert step.routing[0].target_step_id == "done"
    assert (step.failure_policy, step.max_retries, step.timeout_ms) == ("retry", 3, 300000)


# ---- quota headers --------------------------------------------------------


def test_a_response_without_quota_headers_yields_no_snapshot():
    assert QuotaInfo.from_headers({"content-type": "application/json"}) is None


def test_quota_headers_are_read_case_insensitively():
    quota = QuotaInfo.from_headers({"X-Quota-Task-Rate-Limit": "300", "x-quota-workers-remaining": "2"})

    assert quota is not None
    assert quota.task_rate_limit == 300
    assert quota.workers_remaining == 2
    assert quota.queued_tasks_limit is None


def test_the_skill_quotas_are_parsed_alongside_the_task_and_worker_ones():
    quota = QuotaInfo.from_headers(
        {
            "x-quota-skills-limit": "50",
            "x-quota-skills-remaining": "48",
            "x-quota-worker-skills-limit": "10",
            "x-quota-worker-skills-remaining": "7",
        }
    )

    assert quota is not None
    assert (quota.skills_limit, quota.skills_remaining) == (50, 48)
    assert (quota.worker_skills_limit, quota.worker_skills_remaining) == (10, 7)
