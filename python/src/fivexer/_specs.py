"""Request specifications: one pure builder per /v1 operation.

Every operation the SDK exposes is described here exactly once — method, path, body, query.
The sync (:class:`~fivexer.client.Fivexer`) and async (:class:`~fivexer.client.AsyncFivexer`)
clients are thin dispatchers over these builders, so path encoding, body shaping and optional-
field pruning cannot drift between them.

Builders are pure functions of their arguments: no I/O, no client state. That makes the whole
request-construction surface directly unit-testable, which is where most of the SDK's branching
lives.
"""

from __future__ import annotations

from collections.abc import Mapping
from dataclasses import dataclass
from typing import Any, cast
from urllib.parse import quote

from .models import (
    AcceptSupervisorInvite,
    AcceptWorkerInvite,
    AddComment,
    ChangePin,
    CreateAttachment,
    CreateJoinLink,
    CreateNotificationChannel,
    CreateNotificationSequence,
    CreateTask,
    CreateWorkerIdentity,
    InviteWorkerIdentity,
    JoinWorkspace,
    LearningFeedbackItem,
    ListDecisionsQuery,
    ListRunsQuery,
    ListTasksQuery,
    PatchSkill,
    PatchTeam,
    PatchWorker,
    PushSubscriptionInput,
    QueueAuditQuery,
    SetTaskContext,
    StartRun,
    StatsWindowQuery,
    SuggestWorkers,
    UnparkTask,
    UpdateNotificationChannel,
    UpdateNotificationSequence,
    UpdateWorkerIdentity,
    UpsertSkill,
    UpsertTeam,
    UpsertWorker,
    WorkerCreateAttachment,
    WorkerDeviceInput,
    WorkerLocation,
    WorkerLogin,
    WorkerSkillLevel,
    WorkerStatsQuery,
    WorkflowDefinitionInput,
    compact,
    params,
)


@dataclass(frozen=True)
class RequestSpec:
    """A fully-described HTTP call, ready for either client to execute."""

    method: str
    path: str
    body: Any = None
    query: dict[str, str] | None = None


def enc(value: str) -> str:
    """Percent-encode a path segment. ``safe=''`` so ids containing ``/`` cannot escape the path."""
    return quote(value, safe="")


def body_of(value: Any, expected: type) -> dict[str, Any]:
    """Serialise an input model, rejecting a raw dict with a clear error rather than an
    ``AttributeError`` deep in the call. One guard for every input model in the SDK."""
    if not isinstance(value, expected):
        raise TypeError(f"expected {expected.__name__}, got {type(value).__name__}")
    # isinstance() narrows `value` to `object`; the cast keeps `to_json` visible to mypy.
    payload: dict[str, Any] = cast(Any, value).to_json()
    return payload


def _page(cursor: str | None, limit: int | None) -> dict[str, str]:
    return params({"cursor": cursor, "limit": limit})


# ─────────────────────────────── tasks ───────────────────────────────


def tasks_create(task: CreateTask) -> RequestSpec:
    return RequestSpec("POST", "/tasks", body=body_of(task, CreateTask))


def tasks_get(task_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/tasks/{enc(task_id)}")


def tasks_list(query: ListTasksQuery | None) -> RequestSpec:
    return RequestSpec("GET", "/tasks", query=(query or ListTasksQuery()).to_params())


def tasks_cancel(task_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/tasks/{enc(task_id)}")


def tasks_accept(task_id: str, worker_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/accept", body={"workerId": worker_id})


def tasks_reject(task_id: str, worker_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/reject", body={"workerId": worker_id})


def tasks_complete(task_id: str, worker_id: str, result: Mapping[str, Any] | None = None) -> RequestSpec:
    body = compact({"result": None if result is None else dict(result)})
    body["workerId"] = worker_id
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/complete", body=body)


def tasks_assign(task_id: str, worker_id: str, force: bool | None = None) -> RequestSpec:
    body = compact({"force": force})
    body["workerId"] = worker_id
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/assign", body=body)


def tasks_set_priority(task_id: str, priority: float) -> RequestSpec:
    return RequestSpec("PATCH", f"/tasks/{enc(task_id)}", body={"priority": priority})


def tasks_suggest_workers(request: SuggestWorkers) -> RequestSpec:
    return RequestSpec("POST", "/tasks/suggest-workers", body=body_of(request, SuggestWorkers))


# ─────────────────────────────── task context ───────────────────────────────


def context_get(task_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/tasks/{enc(task_id)}/context")


def context_set(task_id: str, context: SetTaskContext) -> RequestSpec:
    return RequestSpec("PUT", f"/tasks/{enc(task_id)}/context", body=body_of(context, SetTaskContext))


def context_clear(task_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/tasks/{enc(task_id)}/context")


# ─────────────────────────────── comments ───────────────────────────────


def comments_add(task_id: str, comment: AddComment) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/comments", body=body_of(comment, AddComment))


def comments_list(task_id: str, cursor: str | None, limit: int | None) -> RequestSpec:
    return RequestSpec("GET", f"/tasks/{enc(task_id)}/comments", query=_page(cursor, limit))


def comments_remove(task_id: str, comment_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/tasks/{enc(task_id)}/comments/{enc(comment_id)}")


# ─────────────────────────────── attachments ───────────────────────────────


def tasks_recurring_list() -> RequestSpec:
    return RequestSpec("GET", "/tasks/recurring")


def tasks_recurring_remove(template_id: str, drop_scheduled: bool | None = None) -> RequestSpec:
    # `dropScheduled` decides the fate of occurrences already cut from the template. Sent only
    # when asked for: the server's default is to leave them alone, and echoing `false` would
    # make a caller who never mentioned them look like one who considered and declined.
    return RequestSpec(
        "DELETE",
        f"/tasks/recurring/{enc(template_id)}",
        query=params({"dropScheduled": "true" if drop_scheduled else None}),
    )


def attachments_create(task_id: str, attachment: CreateAttachment) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/attachments", body=body_of(attachment, CreateAttachment))


def attachments_confirm(task_id: str, attachment_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/attachments/{enc(attachment_id)}/confirm")


def attachments_list(task_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/tasks/{enc(task_id)}/attachments")


def attachments_download(task_id: str, attachment_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/tasks/{enc(task_id)}/attachments/{enc(attachment_id)}/download")


def attachments_remove(task_id: str, attachment_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/tasks/{enc(task_id)}/attachments/{enc(attachment_id)}")


# ─────────────────────────────── workers ───────────────────────────────


def workers_upsert(worker: UpsertWorker | None) -> RequestSpec:
    return RequestSpec("POST", "/workers", body=body_of(worker or UpsertWorker(), UpsertWorker))


def workers_list() -> RequestSpec:
    return RequestSpec("GET", "/workers")


def workers_get(worker_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/workers/{enc(worker_id)}")


def workers_patch(worker_id: str, patch: PatchWorker) -> RequestSpec:
    return RequestSpec("PATCH", f"/workers/{enc(worker_id)}", body=body_of(patch, PatchWorker))


def workers_set_availability(worker_id: str, available: bool, release_backlog: bool = False) -> RequestSpec:
    """`releaseBacklog` is only valid when pausing, so it is emitted only when actually set."""
    body: dict[str, Any] = {"available": available}
    if release_backlog:
        body["releaseBacklog"] = True
    return RequestSpec("POST", f"/workers/{enc(worker_id)}/availability", body=body)


def workers_queue(worker_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/workers/{enc(worker_id)}/queue")


def workers_metrics(worker_id: str, window: str | None = None) -> RequestSpec:
    return RequestSpec("GET", f"/workers/{enc(worker_id)}/metrics", query=params({"window": window}))


def workers_time_entries(worker_id: str, from_: str | None = None, to: str | None = None) -> RequestSpec:
    return RequestSpec("GET", f"/workers/{enc(worker_id)}/time-entries", query=params({"from": from_, "to": to}))


def workers_remove(worker_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/workers/{enc(worker_id)}")


# ─────────────────────────────── skills ───────────────────────────────


def skills_create(skill: UpsertSkill) -> RequestSpec:
    return RequestSpec("POST", "/skills", body=body_of(skill, UpsertSkill))


def skills_list(q: str | None = None, limit: int | None = None) -> RequestSpec:
    return RequestSpec("GET", "/skills", query=params({"q": q, "limit": limit}))


def skills_patch(skill_id: str, patch: PatchSkill) -> RequestSpec:
    return RequestSpec("PATCH", f"/skills/{enc(skill_id)}", body=body_of(patch, PatchSkill))


def skills_remove(skill_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/skills/{enc(skill_id)}")


def skills_suggest(selected: list[str] | None = None, limit: int | None = None) -> RequestSpec:
    """`selected` travels as one comma-joined parameter; an empty list omits it entirely."""
    joined = ",".join(selected) if selected else None
    return RequestSpec("GET", "/skills/suggest", query=params({"selected": joined, "limit": limit}))


# ─────────────────────────────── decisions ───────────────────────────────


def decisions_list(query: ListDecisionsQuery | None) -> RequestSpec:
    return RequestSpec("GET", "/decisions", query=(query or ListDecisionsQuery()).to_params())


# ─────────────────────────────── workflows ───────────────────────────────


def workflows_list() -> RequestSpec:
    return RequestSpec("GET", "/workflows")


def workflows_get(workflow_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/workflows/{enc(workflow_id)}")


def workflows_save(workflow_id: str, definition: WorkflowDefinitionInput) -> RequestSpec:
    return RequestSpec("PUT", f"/workflows/{enc(workflow_id)}", body=body_of(definition, WorkflowDefinitionInput))


def workflows_remove(workflow_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/workflows/{enc(workflow_id)}")


def workflows_run(workflow_id: str, start: StartRun | None) -> RequestSpec:
    return RequestSpec("POST", f"/workflows/{enc(workflow_id)}/runs", body=body_of(start or StartRun(), StartRun))


def workflows_list_runs(workflow_id: str, query: ListRunsQuery | None) -> RequestSpec:
    # workflowId is already in the path; sending it again as a filter would be redundant.
    filters = (query or ListRunsQuery()).to_params()
    filters.pop("workflowId", None)
    return RequestSpec("GET", f"/workflows/{enc(workflow_id)}/runs", query=filters)


# ─────────────────────────────── workflow runs ───────────────────────────────


def runs_list(query: ListRunsQuery | None) -> RequestSpec:
    return RequestSpec("GET", "/workflow-runs", query=(query or ListRunsQuery()).to_params())


def runs_get(run_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/workflow-runs/{enc(run_id)}")


def runs_steps(run_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/workflow-runs/{enc(run_id)}/steps")


def runs_cancel(run_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/workflow-runs/{enc(run_id)}/cancel")


def runs_complete_step(run_id: str, step_id: str, data: Mapping[str, Any] | None = None) -> RequestSpec:
    body = compact({"data": None if data is None else dict(data)})
    return RequestSpec("POST", f"/workflow-runs/{enc(run_id)}/steps/{enc(step_id)}/complete", body=body)


def runs_fail_step(run_id: str, step_id: str, error: str | None = None) -> RequestSpec:
    return RequestSpec(
        "POST", f"/workflow-runs/{enc(run_id)}/steps/{enc(step_id)}/fail", body=compact({"error": error})
    )


# ─────────────────────────────── learning ───────────────────────────────


def learning_status() -> RequestSpec:
    return RequestSpec("GET", "/learning/status")


def learning_worker_stats(worker_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/learning/workers/{enc(worker_id)}")


def learning_preview_weights(
    worker_id: str | None = None,
    override_manual: bool | None = None,
    include_unexplored_tags: bool | None = None,
) -> RequestSpec:
    # Both flags default off server-side, and both widen what a sync would write — one over an
    # operator's hand-set weights, the other onto tags with no reward history. Sending them only
    # when set keeps "I did not ask" distinguishable from "I asked for the default".
    return RequestSpec(
        "GET",
        "/learning/weights/preview",
        query=params(
            {
                "workerId": worker_id,
                "overrideManual": None if override_manual is None else str(override_manual).lower(),
                "includeUnexploredTags": (
                    None if include_unexplored_tags is None else str(include_unexplored_tags).lower()
                ),
            }
        ),
    )


def learning_apply_weights(
    worker_ids: list[str] | None = None,
    override_manual: bool | None = None,
    include_unexplored_tags: bool | None = None,
) -> RequestSpec:
    return RequestSpec(
        "POST",
        "/learning/weights/apply",
        body=compact(
            {
                "workerIds": worker_ids,
                "overrideManual": override_manual,
                "includeUnexploredTags": include_unexplored_tags,
            }
        ),
    )


def learning_feedback(task_id: str, signals: Mapping[str, float]) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/feedback", body={"signals": dict(signals)})


def learning_reward(task_id: str, reward: float) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/reward", body={"reward": reward})


def learning_feedback_bulk(items: list[LearningFeedbackItem]) -> RequestSpec:
    payload = [body_of(item, LearningFeedbackItem) for item in items]
    return RequestSpec("POST", "/learning/feedback", body={"items": payload})


def learning_reset() -> RequestSpec:
    return RequestSpec("POST", "/learning/reset")


# ─────────────────────────────── notifications ───────────────────────────────


def sequences_list() -> RequestSpec:
    return RequestSpec("GET", "/notification-sequences")


def sequences_create(sequence: CreateNotificationSequence) -> RequestSpec:
    return RequestSpec("POST", "/notification-sequences", body=body_of(sequence, CreateNotificationSequence))


def sequences_get(sequence_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/notification-sequences/{enc(sequence_id)}")


def sequences_update(sequence_id: str, update: UpdateNotificationSequence) -> RequestSpec:
    return RequestSpec(
        "PATCH", f"/notification-sequences/{enc(sequence_id)}", body=body_of(update, UpdateNotificationSequence)
    )


def sequences_remove(sequence_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/notification-sequences/{enc(sequence_id)}")


def channels_list() -> RequestSpec:
    return RequestSpec("GET", "/notification-channels")


def channels_create(channel: CreateNotificationChannel) -> RequestSpec:
    return RequestSpec("POST", "/notification-channels", body=body_of(channel, CreateNotificationChannel))


def channels_get(channel_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/notification-channels/{enc(channel_id)}")


def channels_update(channel_id: str, update: UpdateNotificationChannel) -> RequestSpec:
    return RequestSpec(
        "PATCH", f"/notification-channels/{enc(channel_id)}", body=body_of(update, UpdateNotificationChannel)
    )


def channels_remove(channel_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/notification-channels/{enc(channel_id)}")


# ─────────────────────────────── stats ───────────────────────────────


def stats() -> RequestSpec:
    return RequestSpec("GET", "/stats")


def stats_timeseries(query: StatsWindowQuery | None) -> RequestSpec:
    return RequestSpec("GET", "/stats/timeseries", query=(query or StatsWindowQuery()).to_params())


def stats_workers(query: WorkerStatsQuery | StatsWindowQuery | None) -> RequestSpec:
    return RequestSpec("GET", "/stats/workers", query=(query or WorkerStatsQuery()).to_params())


def stats_worker_timeseries(worker_id: str, query: StatsWindowQuery | None) -> RequestSpec:
    return RequestSpec(
        "GET",
        f"/stats/workers/{enc(worker_id)}/timeseries",
        query=(query or StatsWindowQuery()).to_params(),
    )


def team_presence(team_id: str | None = None) -> RequestSpec:
    return RequestSpec("GET", "/team/presence", query=params({"teamId": team_id}))


def breaks_metrics(
    from_: str | None = None,
    to: str | None = None,
    team_id: str | None = None,
    worker_id: str | None = None,
) -> RequestSpec:
    return RequestSpec(
        "GET",
        "/breaks/metrics",
        query=params({"from": from_, "to": to, "teamId": team_id, "workerId": worker_id}),
    )


# ─────────────────────────────── worker portal plane ───────────────────────────────


def worker_login(login: WorkerLogin) -> RequestSpec:
    return RequestSpec("POST", "/worker-auth/login", body=body_of(login, WorkerLogin))


def worker_logout() -> RequestSpec:
    return RequestSpec("POST", "/worker-auth/logout")


def worker_queue(worker_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/portal/workers/{enc(worker_id)}/queue")


def worker_task_detail(task_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/portal/tasks/{enc(task_id)}")


def worker_task_action(task_id: str, action: str, worker_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/portal/tasks/{enc(task_id)}/{action}", body={"workerId": worker_id})


def worker_complete(task_id: str, worker_id: str, result: Mapping[str, Any] | None = None) -> RequestSpec:
    body = compact({"result": None if result is None else dict(result)})
    body["workerId"] = worker_id
    return RequestSpec("POST", f"/portal/tasks/{enc(task_id)}/complete", body=body)


def worker_start_break(reason: str | None = None) -> RequestSpec:
    return RequestSpec("POST", "/portal/breaks/start", body=compact({"reason": reason}))


def worker_end_break() -> RequestSpec:
    return RequestSpec("POST", "/portal/breaks/end")


def worker_breaks_today() -> RequestSpec:
    return RequestSpec("GET", "/portal/breaks/today")


def worker_metrics_today() -> RequestSpec:
    return RequestSpec("GET", "/portal/metrics/today")


def worker_time_entries() -> RequestSpec:
    return RequestSpec("GET", "/portal/me/time-entries")


def worker_team_presence() -> RequestSpec:
    return RequestSpec("GET", "/portal/team/presence")


# ─────────────────────────────── bulk create & dry-run check ───────────────────────────────


def tasks_create_many(tasks: list[CreateTask]) -> RequestSpec:
    return RequestSpec("POST", "/tasks/bulk", body={"tasks": [body_of(t, CreateTask) for t in tasks]})


def tasks_check(task: CreateTask) -> RequestSpec:
    return RequestSpec("POST", "/tasks/check", body=body_of(task, CreateTask))


def tasks_ack(task_id: str, worker_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/ack", body={"workerId": worker_id})


def tasks_escalate(task_id: str, worker_id: str | None = None) -> RequestSpec:
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/escalate", body=compact({"workerId": worker_id}))


def tasks_parked() -> RequestSpec:
    return RequestSpec("GET", "/tasks/parked")


def tasks_scheduled() -> RequestSpec:
    return RequestSpec("GET", "/tasks/scheduled")


def tasks_unpark(task_id: str, options: UnparkTask | None = None) -> RequestSpec:
    body = {} if options is None else body_of(options, UnparkTask)
    return RequestSpec("POST", f"/tasks/{enc(task_id)}/unpark", body=body)


# ─────────────────────────────── teams ───────────────────────────────


def teams_create(team: UpsertTeam) -> RequestSpec:
    return RequestSpec("POST", "/teams", body=body_of(team, UpsertTeam))


def teams_list() -> RequestSpec:
    return RequestSpec("GET", "/teams")


def teams_get(team_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/teams/{enc(team_id)}")


def teams_patch(team_id: str, patch: PatchTeam) -> RequestSpec:
    return RequestSpec("PATCH", f"/teams/{enc(team_id)}", body=body_of(patch, PatchTeam))


def teams_remove(team_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/teams/{enc(team_id)}")


def teams_members(team_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/teams/{enc(team_id)}/members")


def teams_set_members(team_id: str, worker_ids: list[str]) -> RequestSpec:
    return RequestSpec("PUT", f"/teams/{enc(team_id)}/members", body={"workerIds": list(worker_ids)})


# ─────────────────────────────── join links ───────────────────────────────


def join_links_create(link: CreateJoinLink) -> RequestSpec:
    return RequestSpec("POST", "/join-links", body=body_of(link, CreateJoinLink))


def join_links_list() -> RequestSpec:
    return RequestSpec("GET", "/join-links")


def join_links_revoke(link_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/join-links/{enc(link_id)}")


# ─────────────────────────────── worker identities ───────────────────────────────


def identities_list() -> RequestSpec:
    return RequestSpec("GET", "/worker-identities")


def identities_invite(invite: InviteWorkerIdentity) -> RequestSpec:
    return RequestSpec("POST", "/worker-identities/invite", body=body_of(invite, InviteWorkerIdentity))


def identities_resend_invite(worker_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/worker-identities/{enc(worker_id)}/invite/resend")


def identities_create(worker_id: str, identity: CreateWorkerIdentity) -> RequestSpec:
    return RequestSpec("POST", f"/workers/{enc(worker_id)}/identity", body=body_of(identity, CreateWorkerIdentity))


def identities_update(worker_id: str, patch: UpdateWorkerIdentity) -> RequestSpec:
    return RequestSpec("PATCH", f"/workers/{enc(worker_id)}/identity", body=body_of(patch, UpdateWorkerIdentity))


def identities_remove(worker_id: str) -> RequestSpec:
    return RequestSpec("DELETE", f"/workers/{enc(worker_id)}/identity")


# ─────────────────────────────── skills, stats, learning ───────────────────────────────


def skills_get(skill_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/skills/{enc(skill_id)}")


def stats_sla(tag: str | None = None) -> RequestSpec:
    return RequestSpec("GET", "/stats/sla", query=params({"tag": tag}))


def stats_queue_audit(query: QueueAuditQuery | None = None) -> RequestSpec:
    return RequestSpec("GET", "/stats/queue-audit", query=(query or QueueAuditQuery()).to_params())


def learning_revert_weights(worker_ids: list[str] | None = None) -> RequestSpec:
    return RequestSpec("POST", "/learning/weights/revert", body=compact({"workerIds": worker_ids}))


def portal_link() -> RequestSpec:
    return RequestSpec("GET", "/portal")


# ─────────────────────────────── worker portal: self-service ───────────────────────────────


def worker_refresh() -> RequestSpec:
    return RequestSpec("POST", "/worker-auth/refresh")


def worker_join(join: JoinWorkspace) -> RequestSpec:
    return RequestSpec("POST", "/worker-auth/join", body=body_of(join, JoinWorkspace))


def worker_accept_invite(invite: AcceptWorkerInvite) -> RequestSpec:
    return RequestSpec("POST", "/worker-auth/accept-invite", body=body_of(invite, AcceptWorkerInvite))


def worker_me() -> RequestSpec:
    return RequestSpec("GET", "/portal/me")


def worker_set_availability(available: bool, stale_after_ms: int | None = None) -> RequestSpec:
    body: dict[str, Any] = {"available": available}
    # Only ever sent alongside `available: true`, and only by unattended
    # workers: it authorises the platform to clock this worker out after that
    # much silence. A human client must never send it.
    if available and stale_after_ms is not None:
        body["staleAfterMs"] = stale_after_ms
    return RequestSpec("POST", "/portal/me/availability", body=body)


def worker_change_pin(change: ChangePin) -> RequestSpec:
    return RequestSpec("POST", "/portal/me/pin", body=body_of(change, ChangePin))


def worker_skill_catalog() -> RequestSpec:
    return RequestSpec("GET", "/portal/skills")


def worker_set_skills(skills: list[WorkerSkillLevel]) -> RequestSpec:
    return RequestSpec("PUT", "/portal/me/skills", body={"skills": [body_of(s, WorkerSkillLevel) for s in skills]})


def worker_metrics_window(window: str = "7d") -> RequestSpec:
    return RequestSpec("GET", "/portal/metrics", query=params({"window": window}))


def worker_update_location(location: WorkerLocation) -> RequestSpec:
    return RequestSpec("POST", "/portal/workers/me/location", body=body_of(location, WorkerLocation))


def worker_attachments_create(task_id: str, attachment: WorkerCreateAttachment) -> RequestSpec:
    return RequestSpec(
        "POST",
        f"/portal/tasks/{enc(task_id)}/attachments",
        body=body_of(attachment, WorkerCreateAttachment),
    )


def worker_attachments_confirm(task_id: str, attachment_id: str) -> RequestSpec:
    return RequestSpec("POST", f"/portal/tasks/{enc(task_id)}/attachments/{enc(attachment_id)}/confirm")


def worker_attachments_list(task_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/portal/tasks/{enc(task_id)}/attachments")


def worker_attachments_download(task_id: str, attachment_id: str) -> RequestSpec:
    return RequestSpec("GET", f"/portal/tasks/{enc(task_id)}/attachments/{enc(attachment_id)}/download")


def worker_voice_ice() -> RequestSpec:
    """**Experimental — voice is not production-ready.**"""
    return RequestSpec("GET", "/portal/voice/ice")


def worker_comments_list(task_id: str, cursor: str | None, limit: int | None) -> RequestSpec:
    return RequestSpec("GET", f"/portal/tasks/{enc(task_id)}/comments", query=_page(cursor, limit))


def worker_comments_add(task_id: str, body: str) -> RequestSpec:
    return RequestSpec("POST", f"/portal/tasks/{enc(task_id)}/comments", body={"body": body})


def worker_device_register(device: WorkerDeviceInput) -> RequestSpec:
    return RequestSpec("POST", "/portal/devices", body=body_of(device, WorkerDeviceInput))


def worker_device_unregister(token: str) -> RequestSpec:
    return RequestSpec("DELETE", "/portal/devices", body={"token": token})


def worker_push_config() -> RequestSpec:
    return RequestSpec("GET", "/portal/push/config")


def worker_push_subscribe(subscription: PushSubscriptionInput) -> RequestSpec:
    return RequestSpec("POST", "/portal/push/subscriptions", body=body_of(subscription, PushSubscriptionInput))


def worker_push_unsubscribe(endpoint: str) -> RequestSpec:
    # The keys are not sent on unsubscribe: by the time a browser fires
    # `pushsubscriptionchange` it has already discarded them.
    return RequestSpec("DELETE", "/portal/push/subscriptions", body={"endpoint": endpoint})


# ─────────────────────────────── supervisor plane ───────────────────────────────


def supervisor_entry() -> RequestSpec:
    return RequestSpec("GET", "/supervisor-auth/entry")


def supervisor_accept(invite: AcceptSupervisorInvite) -> RequestSpec:
    return RequestSpec("POST", "/supervisor-auth/accept", body=body_of(invite, AcceptSupervisorInvite))


def supervisor_logout() -> RequestSpec:
    return RequestSpec("POST", "/supervisor-auth/logout")


def supervisor_me() -> RequestSpec:
    return RequestSpec("GET", "/supervisor/me")


def supervisor_overview() -> RequestSpec:
    return RequestSpec("GET", "/supervisor/overview")


def supervisor_unpark(task_id: str, options: UnparkTask | None = None) -> RequestSpec:
    body = {} if options is None else body_of(options, UnparkTask)
    return RequestSpec("POST", f"/supervisor/tasks/{enc(task_id)}/unpark", body=body)


def supervisor_set_priority(task_id: str, priority: float) -> RequestSpec:
    return RequestSpec("POST", f"/supervisor/tasks/{enc(task_id)}/priority", body={"priority": priority})


def supervisor_assign(task_id: str, worker_id: str, force: bool | None = None) -> RequestSpec:
    body = compact({"force": force})
    body["workerId"] = worker_id
    return RequestSpec("POST", f"/supervisor/tasks/{enc(task_id)}/assign", body=body)


def supervisor_set_availability(worker_id: str, available: bool, release_backlog: bool | None = None) -> RequestSpec:
    body = compact({"releaseBacklog": release_backlog})
    body["available"] = available
    return RequestSpec("POST", f"/supervisor/workers/{enc(worker_id)}/availability", body=body)


def supervisor_voice_ice() -> RequestSpec:
    """**Experimental — voice is not production-ready.**"""
    return RequestSpec("GET", "/supervisor/voice/ice")


def supervisor_push_config() -> RequestSpec:
    return RequestSpec("GET", "/supervisor/push/config")


def supervisor_push_subscribe(subscription: PushSubscriptionInput) -> RequestSpec:
    return RequestSpec("POST", "/supervisor/push/subscriptions", body=body_of(subscription, PushSubscriptionInput))


def supervisor_push_unsubscribe(endpoint: str) -> RequestSpec:
    return RequestSpec("DELETE", "/supervisor/push/subscriptions", body={"endpoint": endpoint})
