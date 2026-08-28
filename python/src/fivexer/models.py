"""Public /v1 data models, mirrored field-for-field from the platform's OpenAPI spec.

The wire contract is authoritative (``contract/openapi.json`` + ``contract/operations.yaml``,
with the TypeScript ``@fivexer/sdk`` projection in ``platform/packages/sdk/src/types.ts`` as the
reference implementation); these dataclasses are its Python projection. Timestamps are
epoch-milliseconds unless the field name says otherwise (``created_at`` on ``Skill`` and the
break/stats models are ISO-8601 strings, matching the wire).

Two conventions run through this module:

* **Input** models expose ``to_json()`` and build their payload with :func:`compact`, which drops
  ``None`` values. The API distinguishes *absent* from *null*, and every partial-update endpoint
  keeps the stored value for absent fields — so "unset" must never serialise as ``null``.
* **Output** models expose a ``from_json()`` classmethod that tolerates missing optional keys.
"""

from __future__ import annotations

from collections.abc import Mapping
from dataclasses import dataclass, field
from typing import Any, Callable, TypeVar

TaskStatus = str  # 'queued' | 'pending' | 'accepted' | 'completed' | 'cancelled'
RoutingUseCase = str  # only used by /console, kept for completeness
FairnessMode = str  # 'first-come' | 'best-match' | 'balanced' | 'spread-work'
WorkflowRunStatus = str  # 'active' | 'completed' | 'failed' | 'cancelled'
WorkflowTaskType = str  # 'assignment' | 'machine' | 'external'
WorkflowRunStepState = str  # 'completed' | 'active' | 'awaiting_callback' | 'failed' | 'pending'
NotificationTrigger = str  # 'matched' | 'expiring' | 'expired' | 'accepted' | 'rejected' | 'completed'

T = TypeVar("T")


# ─────────────────────────────── serialisation helpers ───────────────────────────────
# Every optional field in every input model would otherwise need its own `if x is not None`
# guard. Concentrating that into two helpers keeps the models declarative and puts the whole
# optional-field branch surface in one place that a handful of tests can pin down.


class _Clear:
    """Sentinel meaning "send an explicit ``null``", as distinct from "leave this field alone".

    Most partial-update endpoints only need *absent*, which :func:`compact` produces by dropping
    ``None``. A few fields are genuinely nullable on the wire — clearing a worker identity's
    email is a real operation — and those cannot express "erase it" any other way.
    """

    __slots__ = ()

    def __repr__(self) -> str:
        return "CLEAR"


#: Typed as ``Any`` so it is assignable to the nullable fields it is meant for without forcing
#: every one of them to widen to ``str | None | _Clear``.
CLEAR: Any = _Clear()


def compact(payload: Mapping[str, Any]) -> dict[str, Any]:
    """Drop ``None``-valued keys — absent and null mean different things to the API."""
    return {key: value for key, value in payload.items() if value is not None}


def params(payload: Mapping[str, Any]) -> dict[str, str]:
    """Stringify query parameters, dropping the ones that were not set."""
    return {key: str(value) for key, value in payload.items() if value is not None}


def _policy(value: Any, parse: Any) -> Any:
    """Parse a nullable policy object. Absent and null both read as ``None`` here: on the way
    *out* of the API the distinction the input side keeps (inherit vs opt out) has already been
    resolved by the server, so a reader only ever sees the effective policy or none at all."""
    return None if value is None else parse.from_json(value)


def _each(data: Mapping[str, Any], key: str, parse: Callable[[Mapping[str, Any]], T]) -> list[T]:
    """Parse a list-valued key, treating missing and null alike as empty."""
    return [parse(item) for item in (data.get(key) or [])]


def _each_or_none(data: Mapping[str, Any], key: str, parse: Callable[[Mapping[str, Any]], T]) -> list[T] | None:
    """Parse a nullable list-valued key, preserving the null/empty distinction."""
    raw = data.get(key)
    return None if raw is None else [parse(item) for item in raw]


def _int_or_none(value: Any) -> int | None:
    return None if value is None else int(value)


def _float_or_none(value: Any) -> float | None:
    return None if value is None else float(value)


# ─────────────────────────────── tasks ───────────────────────────────


@dataclass
class TaskReference:
    """An opaque pointer to data behind your own API — stored and displayed, never fetched."""

    id: str
    url: str
    label: str | None = None
    content_type: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskReference:
        return cls(
            id=data.get("id", ""),
            url=data.get("url", ""),
            label=data.get("label"),
            content_type=data.get("contentType"),
        )


@dataclass
class TaskReferenceInput:
    """A reference being attached to a task. The id is assigned by the API."""

    url: str
    label: str | None = None
    content_type: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact({"url": self.url, "label": self.label, "contentType": self.content_type})


@dataclass
class TaskDataSummary:
    """Counts of the rich data hanging off a task; populated on single-task reads only."""

    has_context: bool
    reference_count: int
    attachment_count: int
    comment_count: int

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskDataSummary:
        return cls(
            has_context=bool(data.get("hasContext", False)),
            reference_count=int(data.get("referenceCount", 0)),
            attachment_count=int(data.get("attachmentCount", 0)),
            comment_count=int(data.get("commentCount", 0)),
        )


@dataclass
class Task:
    id: str
    tags: list[str]
    priority: float | None
    status: TaskStatus
    worker_id: str | None
    created_at: int | None
    meta: dict[str, Any] | None
    # Truncated copy of the rich-data title; the full value lives on tasks.context.get()
    title: str | None = None
    # The hard skill gate this task was created with, in tag->weight form. Without it a
    # readiness check on an existing task silently ignores its own gate and reads too optimistic.
    skill_thresholds: dict[str, float] | None = None
    latitude: float | None = None
    longitude: float | None = None
    max_distance_km: float | None = None
    require_geo: bool | None = None
    allowed_cidrs: list[str] | None = None
    # The policies in force on this task, as stored — resolved workspace defaults included
    escalation: EscalationPolicy | None = None
    # How far up the escalation ladder this task has already climbed
    escalation_level: int | None = None
    sla: SlaPolicy | None = None
    schedule: SchedulePolicy | None = None
    # Set on workflow-step tasks: the run and step this task belongs to
    workflow_run_id: str | None = None
    workflow_step_id: str | None = None
    # Rich-data facts — populated on single-task reads only, never in lists
    data: TaskDataSummary | None = None
    # Present only on archived reads (past the hot window)
    result: dict[str, Any] | None = None
    # True when served from the archive instead of hot storage
    archived: bool = False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Task:
        summary = data.get("data")
        return cls(
            id=data["id"],
            tags=list(data.get("tags") or []),
            priority=data.get("priority"),
            status=data.get("status", "queued"),
            worker_id=data.get("workerId"),
            created_at=data.get("createdAt"),
            meta=data.get("meta"),
            title=data.get("title"),
            latitude=data.get("latitude"),
            longitude=data.get("longitude"),
            max_distance_km=data.get("maxDistanceKm"),
            require_geo=data.get("requireGeo"),
            allowed_cidrs=data.get("allowedCidrs"),
            skill_thresholds=data.get("skillThresholds"),
            escalation=_policy(data.get("escalation"), EscalationPolicy),
            escalation_level=_int_or_none(data.get("escalationLevel")),
            sla=_policy(data.get("sla"), SlaPolicy),
            schedule=_policy(data.get("schedule"), SchedulePolicy),
            workflow_run_id=data.get("workflowRunId"),
            workflow_step_id=data.get("workflowStepId"),
            data=None if summary is None else TaskDataSummary.from_json(summary),
            result=data.get("result"),
            archived=bool(data.get("archived", False)),
        )


class _NullPolicy:
    """Sentinel for an explicit JSON ``null`` on a nullable policy field.

    ``None`` means *absent* — inherit whatever the workspace sets as its default. ``NULL_POLICY``
    means *send null* — opt this task out of the default entirely. They are different wire values
    and different behaviour, so the SDK cannot collapse the two into one.
    """

    __slots__ = ()

    def __repr__(self) -> str:  # pragma: no cover - debugging aid
        return "NULL_POLICY"


#: Opt a task out of a workspace policy default. See :class:`_NullPolicy`.
NULL_POLICY = _NullPolicy()


@dataclass
class EscalationPolicy:
    """What happens when the worker a task was matched to lets the response deadline run out.

    Without a policy the task is simply requeued after the workspace default and the same worker
    may win it straight back — which is the failure mode ``on_no_response="block"`` exists to stop.
    """

    # Milliseconds the matched worker has to respond (1s-24h)
    respond_within_ms: int
    # 'block' stops the non-responder winning it back; 'allow' is the default
    on_no_response: str | None = None
    # Added to the task's priority on every escalation, so an aging task outranks fresh work
    priority_boost: float | None = None
    # Tag sets to widen to, one rung per escalation
    tiers: list[list[str]] | None = None
    max_escalations: int | None = None
    # Where an exhausted ladder leaves the task: back in the queue, or parked for review
    on_exhausted: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "onNoResponse": self.on_no_response,
                "priorityBoost": self.priority_boost,
                "tiers": self.tiers,
                "maxEscalations": self.max_escalations,
                "onExhausted": self.on_exhausted,
            }
        )
        payload["respondWithinMs"] = self.respond_within_ms
        return payload

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> EscalationPolicy:
        tiers = data.get("tiers")
        return cls(
            respond_within_ms=int(data.get("respondWithinMs", 0)),
            on_no_response=data.get("onNoResponse"),
            priority_boost=data.get("priorityBoost"),
            tiers=None if tiers is None else [list(t) for t in tiers],
            max_escalations=_int_or_none(data.get("maxEscalations")),
            on_exhausted=data.get("onExhausted"),
        )


@dataclass
class SlaPolicy:
    """The completion clock, the shelf life and the rejection budget.

    Complements :class:`EscalationPolicy` rather than overlapping it: escalation owns the
    *response* clock, SLA owns everything after. An SLA never gates matching eligibility — a
    breach is reported and acted on, it does not make the task unmatchable.
    """

    # From acceptance. A breach fires once and is handled by on_completion_breach
    complete_within_ms: int | None = None
    # Shelf life from first enqueue — never extended by a requeue
    expire_after_ms: int | None = None
    # Rejections allowed before on_max_rejections applies; outranks the escalation ladder
    max_rejections: int | None = None
    # 'notify' | 'requeue' | 'fail' | 'park'
    on_completion_breach: str | None = None
    # 'park' | 'fail' | 'keep'
    on_max_rejections: str | None = None
    # 'drop' | 'park'
    on_expire: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "completeWithinMs": self.complete_within_ms,
                "expireAfterMs": self.expire_after_ms,
                "maxRejections": self.max_rejections,
                "onCompletionBreach": self.on_completion_breach,
                "onMaxRejections": self.on_max_rejections,
                "onExpire": self.on_expire,
            }
        )

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SlaPolicy:
        return cls(
            complete_within_ms=_int_or_none(data.get("completeWithinMs")),
            expire_after_ms=_int_or_none(data.get("expireAfterMs")),
            max_rejections=_int_or_none(data.get("maxRejections")),
            on_completion_breach=data.get("onCompletionBreach"),
            on_max_rejections=data.get("onMaxRejections"),
            on_expire=data.get("onExpire"),
        )


@dataclass
class SchedulePolicy:
    """When a task may be offered. Unlike escalation and SLA there is no workspace default to
    inherit — these timestamps are absolute epoch-milliseconds."""

    # Held out of matching until this moment; the task reads as 'scheduled' until then
    not_before: int | None = None
    # The offer window closes here; an unserved task is parked or dropped
    not_after: int | None = None
    # 'park' keeps a missed task for review, 'drop' discards it
    on_miss: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact({"notBefore": self.not_before, "notAfter": self.not_after, "onMiss": self.on_miss})

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SchedulePolicy:
        return cls(
            not_before=_int_or_none(data.get("notBefore")),
            not_after=_int_or_none(data.get("notAfter")),
            on_miss=data.get("onMiss"),
        )


@dataclass
class RecurrencePolicy:
    """How a recurring task repeats.

    A task created with a recurrence becomes a standing *template*, never itself matchable: the
    platform materializes each occurrence as an ordinary scheduled task one interval ahead of its
    window. Occurrences align to ``start_at + k x every_ms`` and never drift, so a template that
    was down for an hour resumes on the original grid rather than an hour late.
    """

    # Milliseconds between one occurrence's window opening and the next (min 60s)
    every_ms: int
    # Epoch ms the first window opens. Default: now
    start_at: int | None = None
    # Offer window per occurrence; must be shorter than every_ms
    window_ms: int | None = None
    # What an unserved window does to that occurrence: 'park' | 'drop'
    on_miss: str | None = None
    # No occurrence opens after this epoch ms; the template retires
    until: int | None = None
    max_occurrences: int | None = None
    # 'skip' (default) resumes without back-filling elapsed slots; 'all' materializes them
    catch_up: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "startAt": self.start_at,
                "windowMs": self.window_ms,
                "onMiss": self.on_miss,
                "until": self.until,
                "maxOccurrences": self.max_occurrences,
                "catchUp": self.catch_up,
            }
        )
        payload["everyMs"] = self.every_ms
        return payload

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> RecurrencePolicy:
        return cls(
            every_ms=int(data.get("everyMs", 0)),
            start_at=_int_or_none(data.get("startAt")),
            window_ms=_int_or_none(data.get("windowMs")),
            on_miss=data.get("onMiss"),
            until=_int_or_none(data.get("until")),
            max_occurrences=_int_or_none(data.get("maxOccurrences")),
            catch_up=data.get("catchUp"),
        )


@dataclass
class RecurringTask:
    """A standing template with its clock, from ``client.tasks.recurring.list()``.

    The template is never matchable and never appears in ``tasks.list()`` or the queue stats —
    only the occurrences cut from it are.
    """

    id: str
    tags: list[str]
    recurrence: RecurrencePolicy
    # Epoch ms the next occurrence's window opens
    next_at: int
    # Occurrences materialized so far
    occurrences: int
    priority: float | None = None
    title: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> RecurringTask:
        return cls(
            id=data["id"],
            tags=list(data.get("tags") or []),
            recurrence=RecurrencePolicy.from_json(data.get("recurrence") or {}),
            next_at=int(data.get("nextAt", 0)),
            occurrences=int(data.get("occurrences", 0)),
            priority=data.get("priority"),
            title=data.get("title"),
        )


@dataclass
class CreateTask:
    """Input for ``client.tasks.create``. ``tags`` is required; everything else is optional.

    Carries the rich-data fields too (``title``/``description``/``context``/``references``), so a
    task can be created with its full context in one call instead of a create + context.set pair.
    """

    tags: list[str]
    id: str | None = None
    priority: float | None = None
    skill_thresholds: dict[str, float] | None = None
    # Hard skill gate in catalog terms — the typed twin of skill_thresholds
    required_skills: list[RequiredSkill] | None = None
    vetoed_workers: list[str] | None = None
    meta: dict[str, Any] | None = None
    # Rich data, inlined at creation time
    title: str | None = None
    description: str | None = None
    context: dict[str, Any] | None = None
    references: list[TaskReferenceInput] | None = None
    # Task-side geo constraint: with max_distance_km, the service radius workers must be inside
    latitude: float | None = None
    longitude: float | None = None
    max_distance_km: float | None = None
    # When true, workers without coordinates are excluded from this task
    require_geo: bool | None = None
    # Only workers whose registered IP falls in one of these ranges are eligible
    allowed_cidrs: list[str] | None = None
    # Response clock. Omit to inherit the workspace default; pass ``NULL_POLICY`` to opt out
    escalation: EscalationPolicy | _NullPolicy | None = None
    # Completion clock, shelf life and rejection budget. Same inheritance rule as escalation
    sla: SlaPolicy | _NullPolicy | None = None
    # When this task may be offered. No workspace default to inherit — timestamps are absolute
    schedule: SchedulePolicy | None = None
    # Makes this a standing template instead of a one-off. Mutually exclusive with schedule
    recurrence: RecurrencePolicy | None = None
    # Hard team gate: only members are eligible. Mutually exclusive with prefer_team_id
    team_id: str | None = None
    # Soft team preference: members rank first, everyone else stays eligible
    prefer_team_id: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "id": self.id,
                "priority": self.priority,
                "skillThresholds": self.skill_thresholds,
                "requiredSkills": (
                    None if self.required_skills is None else [r.to_json() for r in self.required_skills]
                ),
                "vetoedWorkers": self.vetoed_workers,
                "meta": self.meta,
                "title": self.title,
                "description": self.description,
                "context": self.context,
                "references": None if self.references is None else [r.to_json() for r in self.references],
                "latitude": self.latitude,
                "longitude": self.longitude,
                "maxDistanceKm": self.max_distance_km,
                "requireGeo": self.require_geo,
                "allowedCidrs": self.allowed_cidrs,
                "schedule": None if self.schedule is None else self.schedule.to_json(),
                "recurrence": None if self.recurrence is None else self.recurrence.to_json(),
                "teamId": self.team_id,
                "preferTeamId": self.prefer_team_id,
            }
        )
        # escalation and sla are nullable on the wire in a way the others are not: an explicit
        # null opts the task out of the workspace default, so NULL_POLICY has to survive compact().
        for name, policy in (("escalation", self.escalation), ("sla", self.sla)):
            if isinstance(policy, _NullPolicy):
                payload[name] = None
            elif policy is not None:
                payload[name] = policy.to_json()
        payload["tags"] = self.tags
        return payload


@dataclass
class TaskPage:
    tasks: list[Task]
    next_cursor: str | None
    has_more: bool

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskPage:
        return cls(
            tasks=_each(data, "tasks", Task.from_json),
            next_cursor=data.get("nextCursor"),
            has_more=bool(data.get("hasMore", False)),
        )


@dataclass
class ListTasksQuery:
    status: str | None = None  # 'queued' | 'pending' | 'accepted' | 'all'
    cursor: str | None = None
    limit: int | None = None

    def to_params(self) -> dict[str, str]:
        return params({"status": self.status, "cursor": self.cursor, "limit": self.limit})


@dataclass
class TaskAction:
    """The result of accept/reject/complete: the task id and its new status."""

    id: str
    status: TaskStatus

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskAction:
        return cls(id=data["id"], status=data.get("status", ""))


@dataclass
class TaskPriority:
    """The result of ``tasks.set_priority``."""

    id: str
    priority: float

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskPriority:
        return cls(id=data["id"], priority=float(data.get("priority", 0)))


@dataclass
class AssignTaskResult:
    """The result of the operator override ``tasks.assign``."""

    id: str
    status: TaskStatus
    worker_id: str
    # The worker who held the task before a reassignment; None when it came from the queue
    previous_worker_id: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> AssignTaskResult:
        return cls(
            id=data["id"],
            status=data.get("status", "pending"),
            worker_id=data.get("workerId", ""),
            previous_worker_id=data.get("previousWorkerId"),
        )


# ─────────────────────────────── task context ───────────────────────────────


@dataclass
class TaskContext:
    task_id: str
    title: str | None = None
    description: str | None = None
    context: dict[str, Any] | None = None
    references: list[TaskReference] = field(default_factory=list)
    created_at: int | None = None
    updated_at: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskContext:
        return cls(
            task_id=data.get("taskId", ""),
            title=data.get("title"),
            description=data.get("description"),
            context=data.get("context"),
            references=_each(data, "references", TaskReference.from_json),
            created_at=data.get("createdAt"),
            updated_at=data.get("updatedAt"),
        )


@dataclass
class SetTaskContext:
    """Input for ``client.tasks.context.set`` — a full replace of the task's rich data."""

    title: str | None = None
    description: str | None = None
    context: dict[str, Any] | None = None
    references: list[TaskReferenceInput] | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "title": self.title,
                "description": self.description,
                "context": self.context,
                "references": None if self.references is None else [r.to_json() for r in self.references],
            }
        )


# ─────────────────────────────── comments ───────────────────────────────


@dataclass
class CommentAuthor:
    """``user`` = console human, ``worker`` = on behalf of a worker, ``api`` = the integration."""

    type: str
    id: str | None = None
    label: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> CommentAuthor:
        return cls(type=data.get("type", "api"), id=data.get("id"), label=data.get("label"))


@dataclass
class Comment:
    id: str
    task_id: str
    author: CommentAuthor
    body: str
    created_at: int

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Comment:
        return cls(
            id=data["id"],
            task_id=data.get("taskId", ""),
            author=CommentAuthor.from_json(data.get("author") or {}),
            body=data.get("body", ""),
            created_at=int(data.get("createdAt", 0)),
        )


@dataclass
class AddComment:
    body: str
    # Attribute the comment to a worker (API-key callers only)
    worker_id: str | None = None
    author_label: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"workerId": self.worker_id, "authorLabel": self.author_label})
        payload["body"] = self.body
        return payload


@dataclass
class CommentPage:
    comments: list[Comment]
    next_cursor: str | None
    has_more: bool

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> CommentPage:
        return cls(
            comments=_each(data, "comments", Comment.from_json),
            next_cursor=data.get("nextCursor"),
            has_more=bool(data.get("hasMore", False)),
        )


# ─────────────────────────────── attachments ───────────────────────────────


@dataclass
class AttachmentUploader:
    type: str
    id: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> AttachmentUploader:
        return cls(type=data.get("type", "api"), id=data.get("id"))


@dataclass
class Attachment:
    id: str
    task_id: str
    filename: str
    content_type: str
    size_bytes: int
    # 'pending' until the upload is confirmed against object storage, then 'ready'
    status: str
    uploader: AttachmentUploader
    created_at: int
    confirmed_at: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Attachment:
        return cls(
            id=data["id"],
            task_id=data.get("taskId", ""),
            filename=data.get("filename", ""),
            content_type=data.get("contentType", ""),
            size_bytes=int(data.get("sizeBytes", 0)),
            status=data.get("status", "pending"),
            uploader=AttachmentUploader.from_json(data.get("uploader") or {}),
            created_at=int(data.get("createdAt", 0)),
            confirmed_at=_int_or_none(data.get("confirmedAt")),
        )


@dataclass
class CreateAttachment:
    filename: str
    content_type: str
    size_bytes: int
    # Attribute the upload to a worker (API-key callers only)
    worker_id: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"workerId": self.worker_id})
        payload.update({"filename": self.filename, "contentType": self.content_type, "sizeBytes": self.size_bytes})
        return payload


@dataclass
class WorkerCreateAttachment:
    """Worker-plane upload input.

    The same shape as :class:`CreateAttachment` minus ``worker_id``: on this plane the uploader
    is the session, so a worker cannot attribute a file to anyone else.
    """

    filename: str
    content_type: str
    size_bytes: int

    def to_json(self) -> dict[str, Any]:
        return {"filename": self.filename, "contentType": self.content_type, "sizeBytes": self.size_bytes}


@dataclass
class AttachmentUpload:
    """A presigned upload target. Send ``headers`` exactly as given — they are signed."""

    url: str
    method: str
    headers: dict[str, str] = field(default_factory=dict)
    expires_at: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> AttachmentUpload:
        return cls(
            url=data.get("url", ""),
            method=data.get("method", "PUT"),
            headers=dict(data.get("headers") or {}),
            expires_at=int(data.get("expiresAt", 0)),
        )


@dataclass
class CreatedAttachment:
    """``attachments.create`` returns the record plus where to PUT the bytes."""

    attachment: Attachment
    upload: AttachmentUpload

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> CreatedAttachment:
        return cls(
            attachment=Attachment.from_json(data.get("attachment") or {}),
            upload=AttachmentUpload.from_json(data.get("upload") or {}),
        )


@dataclass
class AttachmentDownload:
    url: str
    expires_at: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> AttachmentDownload:
        return cls(url=data.get("url", ""), expires_at=int(data.get("expiresAt", 0)))


# ─────────────────────────────── skills ───────────────────────────────


@dataclass
class Skill:
    id: str
    key: str
    name: str
    description: str | None = None
    created_at: str = ""  # ISO-8601

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Skill:
        return cls(
            id=data["id"],
            key=data.get("key", ""),
            name=data.get("name", ""),
            description=data.get("description"),
            created_at=data.get("createdAt", ""),
        )


@dataclass
class UpsertSkill:
    key: str
    name: str
    description: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"description": self.description})
        payload.update({"key": self.key, "name": self.name})
        return payload


@dataclass
class PatchSkill:
    """Partial update — absent fields keep their stored value. ``key`` is immutable."""

    name: str | None = None
    description: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact({"name": self.name, "description": self.description})


@dataclass
class WorkerSkill:
    """A skill as held by a worker, with the effective routing weight it contributes."""

    skill_id: str
    key: str
    name: str
    level: int
    weight: float
    weight_override: float | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerSkill:
        return cls(
            skill_id=data.get("skillId", ""),
            key=data.get("key", ""),
            name=data.get("name", ""),
            level=int(data.get("level", 0)),
            weight=float(data.get("weight", 0)),
            weight_override=_float_or_none(data.get("weightOverride")),
        )


@dataclass
class WorkerSkillAssignment:
    """Assign a skill to a worker. ``level`` is 1–5 (novice → expert); the API validates it."""

    skill_id: str
    level: int
    weight_override: float | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"weightOverride": self.weight_override})
        payload.update({"skillId": self.skill_id, "level": self.level})
        return payload


# ─────────────────────────────── workers ───────────────────────────────


@dataclass
class UpsertWorker:
    id: str | None = None
    tags: list[str] | None = None
    routing_weights: dict[str, float] | None = None
    skills: list[WorkerSkillAssignment] | None = None
    ip: str | None = None
    latitude: float | None = None
    longitude: float | None = None
    max_travel_distance_km: float | None = None
    # Per-worker backlog cap overriding the workspace default (0 = receive nothing)
    max_backlog_size: int | None = None
    # Replaces team membership wholesale. Omit to leave it untouched; [] clears it
    team_ids: list[str] | None = None
    # Shift state. A *new* worker is created off shift and is not matched until they go
    # available from the portal or an operator resumes them — availability is a claim a person
    # makes, not a side effect of existing. Pass True to create an already-available worker in
    # one call: the escape hatch for programmatic fleets with no human at a portal. On an
    # update, omit it to leave the worker's current shift state untouched.
    available: bool | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "id": self.id,
                "tags": self.tags,
                "routingWeights": self.routing_weights,
                "skills": None if self.skills is None else [s.to_json() for s in self.skills],
                "teamIds": self.team_ids,
                "ip": self.ip,
                "latitude": self.latitude,
                "longitude": self.longitude,
                "maxTravelDistanceKm": self.max_travel_distance_km,
                "maxBacklogSize": self.max_backlog_size,
                "available": self.available,
            }
        )


@dataclass
class PatchWorker:
    """Partial in-place update — absent fields keep their stored value. The id comes from the URL."""

    tags: list[str] | None = None
    routing_weights: dict[str, float] | None = None
    skills: list[WorkerSkillAssignment] | None = None
    ip: str | None = None
    latitude: float | None = None
    longitude: float | None = None
    max_travel_distance_km: float | None = None
    max_backlog_size: int | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "tags": self.tags,
                "routingWeights": self.routing_weights,
                "skills": None if self.skills is None else [s.to_json() for s in self.skills],
                "ip": self.ip,
                "latitude": self.latitude,
                "longitude": self.longitude,
                "maxTravelDistanceKm": self.max_travel_distance_km,
                "maxBacklogSize": self.max_backlog_size,
            }
        )


@dataclass
class WorkerTeam:
    """One team membership as it appears on a worker's detail record."""

    team_id: str
    key: str
    name: str
    color: str | None = None
    # 'member' | 'lead'
    role: str = "member"

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTeam:
        return cls(
            team_id=data.get("teamId", ""),
            key=data.get("key", ""),
            name=data.get("name", ""),
            color=data.get("color"),
            role=data.get("role", "member"),
        )


@dataclass
class WorkerDetail:
    id: str
    tags: list[str] = field(default_factory=list)
    routing_weights: dict[str, float] | None = None
    # Which entries of routing_weights the learning layer owns, and when it last wrote them —
    # the difference between "an operator vetoed this tag" and "the model did".
    learned_routing_weights: dict[str, float] | None = None
    # What routing_weights held before the last sync, restorable via learning.revert_weights()
    routing_weights_snapshot: dict[str, float] | None = None
    learned_routing_weights_synced_at: int | None = None
    skills: list[WorkerSkill] | None = None
    teams: list[WorkerTeam] | None = None
    max_backlog_size: int | None = None
    available: bool = True
    # Why they are unavailable when it is not simply "off shift": an invite nobody accepted,
    # or a QR join waiting on operator approval. Both must read as themselves, not as "paused".
    invite_pending: bool = False
    pending_approval: bool = False
    queue_depth: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerDetail:
        return cls(
            id=data["id"],
            tags=list(data.get("tags") or []),
            routing_weights=data.get("routingWeights"),
            learned_routing_weights=data.get("learnedRoutingWeights"),
            routing_weights_snapshot=data.get("routingWeightsSnapshot"),
            learned_routing_weights_synced_at=_int_or_none(data.get("learnedRoutingWeightsSyncedAt")),
            skills=_each_or_none(data, "skills", WorkerSkill.from_json),
            teams=_each_or_none(data, "teams", WorkerTeam.from_json),
            max_backlog_size=_int_or_none(data.get("maxBacklogSize")),
            available=bool(data.get("available", True)),
            invite_pending=bool(data.get("invitePending", False)),
            pending_approval=bool(data.get("pendingApproval", False)),
            queue_depth=int(data.get("queueDepth", 0)),
        )


@dataclass
class WorkerAvailability:
    """The result of pausing or resuming a worker."""

    id: str
    available: bool
    # Only present when the pause requested a backlog release
    released_task_ids: list[str] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerAvailability:
        released = data.get("releasedTaskIds")
        return cls(
            id=data["id"],
            available=bool(data.get("available", False)),
            released_task_ids=None if released is None else list(released),
        )


@dataclass
class WorkerQueue:
    worker_id: str
    task_ids: list[str] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerQueue:
        return cls(worker_id=data["workerId"], task_ids=list(data.get("taskIds") or []))


@dataclass
class WorkerList:
    workers: list[str]
    count: int

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerList:
        return cls(workers=list(data.get("workers") or []), count=int(data.get("count", 0)))


# ─────────────────────────────── worker suggestion (dry run) ───────────────────────────────


@dataclass
class RequiredSkill:
    skill_id: str
    min_level: int

    def to_json(self) -> dict[str, Any]:
        return {"skillId": self.skill_id, "minLevel": self.min_level}


@dataclass
class SuggestWorkers:
    """Dry-run scoring: who *would* match these tags, without creating a task."""

    tags: list[str]
    priority: float | None = None
    required_skills: list[RequiredSkill] | None = None
    vetoed_workers: list[str] | None = None
    limit: int | None = None
    latitude: float | None = None
    longitude: float | None = None
    max_distance_km: float | None = None
    require_geo: bool | None = None
    allowed_cidrs: list[str] | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "priority": self.priority,
                "requiredSkills": (
                    None if self.required_skills is None else [s.to_json() for s in self.required_skills]
                ),
                "vetoedWorkers": self.vetoed_workers,
                "limit": self.limit,
                "latitude": self.latitude,
                "longitude": self.longitude,
                "maxDistanceKm": self.max_distance_km,
                "requireGeo": self.require_geo,
                "allowedCidrs": self.allowed_cidrs,
            }
        )
        payload["tags"] = self.tags
        return payload


@dataclass
class SuggestedWorker:
    worker_id: str
    eligible: bool
    score: float
    effective_priority: float
    # Free-form scoring explanations; shape varies by rule
    reasons: list[dict[str, Any]] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SuggestedWorker:
        return cls(
            worker_id=data.get("workerId", ""),
            eligible=bool(data.get("eligible", False)),
            score=float(data.get("score", 0)),
            effective_priority=float(data.get("effectivePriority", 0)),
            reasons=[dict(r) for r in (data.get("reasons") or [])],
        )


@dataclass
class SuggestWorkersResult:
    tags: list[str]
    priority: float
    workers: list[SuggestedWorker] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SuggestWorkersResult:
        return cls(
            tags=list(data.get("tags") or []),
            priority=float(data.get("priority", 0)),
            workers=_each(data, "workers", SuggestedWorker.from_json),
        )


# ─────────────────────────────── decisions ───────────────────────────────


@dataclass
class DecisionCandidate:
    worker_id: str
    detail: dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> DecisionCandidate:
        return cls(worker_id=data["workerId"], detail={k: v for k, v in data.items() if k != "workerId"})


@dataclass
class Decision:
    id: str
    task_id: str
    worker_id: str
    matched_at: int
    mode: str
    candidates: list[DecisionCandidate] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Decision:
        return cls(
            id=data["id"],
            task_id=data["taskId"],
            worker_id=data["workerId"],
            matched_at=int(data["matchedAt"]),
            mode=data.get("mode", ""),
            candidates=_each(data, "candidates", DecisionCandidate.from_json),
        )


@dataclass
class ListDecisionsQuery:
    task_id: str | None = None
    worker_id: str | None = None
    limit: int | None = None

    def to_params(self) -> dict[str, str]:
        return params({"taskId": self.task_id, "workerId": self.worker_id, "limit": self.limit})


# ─────────────────────────────── workflows ───────────────────────────────


@dataclass
class WorkflowRouting:
    """A branch rule. ``condition`` is evaluated against the step result, first match wins."""

    condition: str
    target_step_id: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRouting:
        return cls(condition=data.get("condition", ""), target_step_id=data.get("targetStepId", ""))

    def to_json(self) -> dict[str, Any]:
        return {"condition": self.condition, "targetStepId": self.target_step_id}


@dataclass
class WorkflowStep:
    """One node of a workflow graph.

    ``default_next_step_id`` is genuinely tri-state: absent means "no fallback declared",
    while an explicit ``None`` ends the workflow. ``end_workflow`` expresses the latter, since
    a plain ``None`` attribute cannot be told apart from "not set".
    """

    id: str
    name: str
    task_type: WorkflowTaskType | None = None
    assignment_template: dict[str, Any] | None = None
    # 'initiator' | 'previous' | a worker id | {'tag': '...'}
    target_user: Any = None
    machine_task: dict[str, Any] | None = None
    external: dict[str, Any] | None = None
    routing: list[WorkflowRouting] | None = None
    default_next_step_id: str | None = None
    # Serialise `defaultNextStepId: null`, which ends the workflow after this step
    end_workflow: bool = False
    parallel_step_ids: list[str] | None = None
    wait_for_all: bool | None = None
    failure_policy: str | None = None  # 'abort' | 'continue' | 'retry'
    max_retries: int | None = None
    timeout_ms: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowStep:
        return cls(
            id=data.get("id", ""),
            name=data.get("name", ""),
            task_type=data.get("taskType"),
            assignment_template=data.get("assignmentTemplate"),
            target_user=data.get("targetUser"),
            machine_task=data.get("machineTask"),
            external=data.get("external"),
            routing=_each_or_none(data, "routing", WorkflowRouting.from_json),
            default_next_step_id=data.get("defaultNextStepId"),
            end_workflow="defaultNextStepId" in data and data["defaultNextStepId"] is None,
            parallel_step_ids=data.get("parallelStepIds"),
            wait_for_all=data.get("waitForAll"),
            failure_policy=data.get("failurePolicy"),
            max_retries=_int_or_none(data.get("maxRetries")),
            timeout_ms=_int_or_none(data.get("timeoutMs")),
        )

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "taskType": self.task_type,
                "assignmentTemplate": self.assignment_template,
                "targetUser": self.target_user,
                "machineTask": self.machine_task,
                "external": self.external,
                "routing": None if self.routing is None else [r.to_json() for r in self.routing],
                "defaultNextStepId": self.default_next_step_id,
                "parallelStepIds": self.parallel_step_ids,
                "waitForAll": self.wait_for_all,
                "failurePolicy": self.failure_policy,
                "maxRetries": self.max_retries,
                "timeoutMs": self.timeout_ms,
            }
        )
        payload.update({"id": self.id, "name": self.name})
        if self.end_workflow:
            payload["defaultNextStepId"] = None
        return payload


@dataclass
class WorkflowDefinition:
    id: str
    name: str
    version: int
    initial_step_id: str
    steps: list[WorkflowStep] = field(default_factory=list)
    default_timeout_ms: int | None = None
    metadata: dict[str, Any] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowDefinition:
        return cls(
            id=data["id"],
            name=data.get("name", ""),
            version=int(data.get("version", 1)),
            initial_step_id=data.get("initialStepId", ""),
            steps=_each(data, "steps", WorkflowStep.from_json),
            default_timeout_ms=_int_or_none(data.get("defaultTimeoutMs")),
            metadata=data.get("metadata"),
        )


@dataclass
class WorkflowDefinitionInput:
    """The shape accepted by ``workflows.save`` — the id comes from the URL, never the body."""

    name: str
    steps: list[WorkflowStep]
    version: int | None = None
    initial_step_id: str | None = None
    default_timeout_ms: int | None = None
    metadata: dict[str, Any] | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "version": self.version,
                "initialStepId": self.initial_step_id,
                "defaultTimeoutMs": self.default_timeout_ms,
                "metadata": self.metadata,
            }
        )
        payload.update({"name": self.name, "steps": [s.to_json() for s in self.steps]})
        return payload


@dataclass
class WorkflowDefinitionSummary:
    id: str
    name: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowDefinitionSummary:
        return cls(id=data["id"], name=data.get("name", ""))


# ─────────────────────────────── workflow runs ───────────────────────────────


@dataclass
class WorkflowRunHistoryEntry:
    step_id: str
    task_id: str
    worker_id: str
    completed_at: int
    result: dict[str, Any] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRunHistoryEntry:
        return cls(
            step_id=data.get("stepId", ""),
            task_id=data.get("taskId", ""),
            worker_id=data.get("workerId", ""),
            completed_at=int(data.get("completedAt", 0)),
            result=data.get("result"),
        )


@dataclass
class ParallelBranch:
    step_id: str
    assignment_id: str
    status: str
    result: dict[str, Any] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> ParallelBranch:
        return cls(
            step_id=data.get("stepId", ""),
            assignment_id=data.get("assignmentId", ""),
            status=data.get("status", ""),
            result=data.get("result"),
        )


@dataclass
class WorkflowRun:
    id: str
    workflow_id: str
    status: WorkflowRunStatus
    initiator_worker_id: str
    definition_version: int
    created_at: int
    updated_at: int
    current_step_id: str | None = None
    current_task_id: str | None = None
    context: dict[str, Any] = field(default_factory=dict)
    history: list[WorkflowRunHistoryEntry] = field(default_factory=list)
    parallel_branches: list[ParallelBranch] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRun:
        return cls(
            id=data["id"],
            workflow_id=data.get("workflowId", ""),
            status=data.get("status", ""),
            initiator_worker_id=data.get("initiatorWorkerId", ""),
            definition_version=int(data.get("definitionVersion", 1)),
            created_at=int(data.get("createdAt", 0)),
            updated_at=int(data.get("updatedAt", 0)),
            current_step_id=data.get("currentStepId"),
            current_task_id=data.get("currentTaskId"),
            context=dict(data.get("context") or {}),
            history=_each(data, "history", WorkflowRunHistoryEntry.from_json),
            parallel_branches=_each_or_none(data, "parallelBranches", ParallelBranch.from_json),
        )


@dataclass
class WorkflowRunPage:
    runs: list[WorkflowRun]
    next_cursor: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRunPage:
        return cls(runs=_each(data, "runs", WorkflowRun.from_json), next_cursor=data.get("nextCursor"))


@dataclass
class WorkflowRunStep:
    step_id: str
    name: str
    task_type: WorkflowTaskType
    state: WorkflowRunStepState
    task_id: str | None = None
    worker_id: str | None = None
    completed_at: int | None = None
    result: dict[str, Any] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRunStep:
        return cls(
            step_id=data.get("stepId", ""),
            name=data.get("name", ""),
            task_type=data.get("taskType", ""),
            state=data.get("state", ""),
            task_id=data.get("taskId"),
            worker_id=data.get("workerId"),
            completed_at=_int_or_none(data.get("completedAt")),
            result=data.get("result"),
        )


@dataclass
class WorkflowRunSteps:
    """The per-step view of a run — what the console canvas paints."""

    run_id: str
    status: WorkflowRunStatus
    steps: list[WorkflowRunStep] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkflowRunSteps:
        return cls(
            run_id=data.get("runId", ""),
            status=data.get("status", ""),
            steps=_each(data, "steps", WorkflowRunStep.from_json),
        )


@dataclass
class StartRun:
    context: dict[str, Any] | None = None
    # Worker to start the run as; required when a step assigns to the initiator
    initiator_worker_id: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact({"context": self.context, "initiatorWorkerId": self.initiator_worker_id})


@dataclass
class ListRunsQuery:
    status: WorkflowRunStatus | None = None
    workflow_id: str | None = None
    cursor: str | None = None
    limit: int | None = None

    def to_params(self) -> dict[str, str]:
        return params(
            {
                "status": self.status,
                "workflowId": self.workflow_id,
                "cursor": self.cursor,
                "limit": self.limit,
            }
        )


# ─────────────────────────────── learning ───────────────────────────────


@dataclass
class LearningStats:
    decisions: int
    rewards: int
    total_reward: float
    average_reward: float

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> LearningStats:
        return cls(
            decisions=int(data.get("decisions", 0)),
            rewards=int(data.get("rewards", 0)),
            total_reward=float(data.get("totalReward", 0)),
            average_reward=float(data.get("averageReward", 0)),
        )


@dataclass
class LearningStatus:
    enabled: bool
    shadow_mode: bool
    auto_weights: bool
    model_size: int = 0
    signal_weights: dict[str, float] | None = None
    rewards: dict[str, float] | None = None
    # None until the first reward lands
    stats: LearningStats | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> LearningStatus:
        stats = data.get("stats")
        return cls(
            enabled=bool(data.get("enabled", False)),
            shadow_mode=bool(data.get("shadowMode", False)),
            auto_weights=bool(data.get("autoWeights", False)),
            model_size=int(data.get("modelSize", 0)),
            signal_weights=data.get("signalWeights"),
            rewards=data.get("rewards"),
            stats=None if stats is None else LearningStats.from_json(stats),
        )


@dataclass
class WorkerLearningSkillStat:
    tag: str
    count: int
    mean_reward: float
    current_weight: float | None = None
    learned_weight: float | None = None
    # Set when the tag maps to a registered skill rather than a plain routing tag
    skill: dict[str, Any] | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerLearningSkillStat:
        return cls(
            tag=data.get("tag", ""),
            count=int(data.get("count", 0)),
            mean_reward=float(data.get("meanReward", 0)),
            current_weight=_float_or_none(data.get("currentWeight")),
            learned_weight=_float_or_none(data.get("learnedWeight")),
            skill=data.get("skill"),
        )


@dataclass
class WorkerLearningStats:
    worker_id: str
    skills: list[WorkerLearningSkillStat] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerLearningStats:
        return cls(
            worker_id=data.get("workerId", ""),
            skills=_each(data, "skills", WorkerLearningSkillStat.from_json),
        )


@dataclass
class PreviewedWorkerWeights:
    worker_id: str
    current: dict[str, float] = field(default_factory=dict)
    learned: dict[str, float] = field(default_factory=dict)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> PreviewedWorkerWeights:
        return cls(
            worker_id=data.get("workerId", ""),
            current=dict(data.get("current") or {}),
            learned=dict(data.get("learned") or {}),
        )


@dataclass
class LearnedWeightsPreview:
    workers: list[PreviewedWorkerWeights] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> LearnedWeightsPreview:
        return cls(workers=_each(data, "workers", PreviewedWorkerWeights.from_json))


@dataclass
class LearningFeedbackItem:
    """One entry of a bulk feedback submission. Send signals, a reward, or both."""

    task_id: str
    signals: dict[str, float] | None = None
    reward: float | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"signals": self.signals, "reward": self.reward})
        payload["taskId"] = self.task_id
        return payload


@dataclass
class LearningFeedbackResult:
    """Bulk feedback is partial-success: each item reports its own outcome."""

    task_id: str
    ok: bool
    error: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> LearningFeedbackResult:
        return cls(task_id=data.get("taskId", ""), ok=bool(data.get("ok", False)), error=data.get("error"))


# ─────────────────────────────── notifications ───────────────────────────────


@dataclass
class NotificationSequenceStep:
    trigger: NotificationTrigger
    event_type: str
    offset_ms: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> NotificationSequenceStep:
        return cls(
            trigger=data.get("trigger", ""),
            event_type=data.get("eventType", ""),
            offset_ms=_int_or_none(data.get("offsetMs")),
        )

    def to_json(self) -> dict[str, Any]:
        payload = compact({"offsetMs": self.offset_ms})
        payload.update({"trigger": self.trigger, "eventType": self.event_type})
        return payload


@dataclass
class NotificationSequence:
    id: str
    name: str
    enabled: bool
    steps: list[NotificationSequenceStep] = field(default_factory=list)
    filter_tags: list[str] = field(default_factory=list)
    created_at: str = ""  # ISO-8601
    updated_at: str = ""  # ISO-8601

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> NotificationSequence:
        return cls(
            id=data["id"],
            name=data.get("name", ""),
            enabled=bool(data.get("enabled", False)),
            steps=_each(data, "steps", NotificationSequenceStep.from_json),
            filter_tags=list(data.get("filterTags") or []),
            created_at=data.get("createdAt", ""),
            updated_at=data.get("updatedAt", ""),
        )


@dataclass
class CreateNotificationSequence:
    name: str
    steps: list[NotificationSequenceStep]
    enabled: bool | None = None
    filter_tags: list[str] | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"enabled": self.enabled, "filterTags": self.filter_tags})
        payload.update({"name": self.name, "steps": [s.to_json() for s in self.steps]})
        return payload


@dataclass
class UpdateNotificationSequence:
    """Partial update — absent fields keep their stored value."""

    name: str | None = None
    enabled: bool | None = None
    steps: list[NotificationSequenceStep] | None = None
    filter_tags: list[str] | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "name": self.name,
                "enabled": self.enabled,
                "steps": None if self.steps is None else [s.to_json() for s in self.steps],
                "filterTags": self.filter_tags,
            }
        )


@dataclass
class NotificationChannel:
    id: str
    type: str  # 'webhook' | 'websocket'
    target: str
    events: list[str] = field(default_factory=list)
    disabled: bool = False
    created_at: str = ""  # ISO-8601

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> NotificationChannel:
        return cls(
            id=data["id"],
            type=data.get("type", ""),
            target=data.get("target", ""),
            events=list(data.get("events") or []),
            disabled=bool(data.get("disabled", False)),
            created_at=data.get("createdAt", ""),
        )


@dataclass
class CreateNotificationChannel:
    type: str  # 'webhook' | 'websocket'
    target: str
    events: list[str]
    # Write-only: never echoed back on a read
    secret: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"secret": self.secret})
        payload.update({"type": self.type, "target": self.target, "events": self.events})
        return payload


@dataclass
class UpdateNotificationChannel:
    """Partial update — absent fields keep their stored value."""

    type: str | None = None
    target: str | None = None
    events: list[str] | None = None
    secret: str | None = None
    disabled: bool | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "type": self.type,
                "target": self.target,
                "events": self.events,
                "secret": self.secret,
                "disabled": self.disabled,
            }
        )


# ─────────────────────────────── stats ───────────────────────────────


@dataclass
class WorkerLoad:
    worker_id: str
    backlog: int
    max_backlog_size: int
    available: bool
    # Same distinction as on WorkerDetail: not-yet-accepted invite, or a join awaiting approval
    invite_pending: bool = False
    pending_approval: bool = False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerLoad:
        return cls(
            worker_id=data.get("workerId", ""),
            backlog=int(data.get("backlog", 0)),
            max_backlog_size=int(data.get("maxBacklogSize", 0)),
            available=bool(data.get("available", False)),
            invite_pending=bool(data.get("invitePending", False)),
            pending_approval=bool(data.get("pendingApproval", False)),
        )


@dataclass
class QueueStats:
    # Age of the longest-waiting unaccepted task in ms; None when the queue is empty
    oldest_waiting_ms: int | None = None
    per_worker: list[WorkerLoad] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> QueueStats:
        return cls(
            oldest_waiting_ms=_int_or_none(data.get("oldestWaitingMs")),
            per_worker=_each(data, "perWorker", WorkerLoad.from_json),
        )


@dataclass
class MatchingPolicy:
    """The live balancer policy applied to bulk matching passes."""

    fairness: FairnessMode = "first-come"
    max_tasks_per_window: int | None = None
    window_ms: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> MatchingPolicy:
        return cls(
            fairness=data.get("fairness", "first-come"),
            max_tasks_per_window=_int_or_none(data.get("maxTasksPerWindow")),
            window_ms=_int_or_none(data.get("windowMs")),
        )


@dataclass
class WorkspaceStats:
    plan: str
    tasks: dict[str, int]
    workers: int
    meter_period: str
    meter_matched_tasks: int
    meter_included_tasks_per_month: int
    queue: QueueStats = field(default_factory=QueueStats)
    matching: MatchingPolicy = field(default_factory=MatchingPolicy)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkspaceStats:
        meter = data.get("meter") or {}
        return cls(
            plan=data.get("plan", ""),
            tasks={k: int(v) for k, v in (data.get("tasks") or {}).items()},
            workers=int(data.get("workers", 0)),
            meter_period=meter.get("period", ""),
            meter_matched_tasks=int(meter.get("matchedTasks", 0)),
            meter_included_tasks_per_month=int(meter.get("includedTasksPerMonth", 0)),
            queue=QueueStats.from_json(data.get("queue") or {}),
            matching=MatchingPolicy.from_json(data.get("matching") or {}),
        )


@dataclass
class StatsWindowQuery:
    """Window for the historical stats endpoints; defaults to the last 24h bucketed hourly."""

    from_: str | None = None  # ISO-8601
    to: str | None = None  # ISO-8601
    bucket: str | None = None  # 'hour' | 'day'

    def to_params(self) -> dict[str, str]:
        return params({"from": self.from_, "to": self.to, "bucket": self.bucket})


@dataclass
class StatsTimeseriesBucket:
    bucket_start: str
    completed: int
    cancelled: int
    avg_wait_ms: float | None = None
    p50_wait_ms: float | None = None
    p95_wait_ms: float | None = None
    avg_handle_ms: float | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> StatsTimeseriesBucket:
        return cls(
            bucket_start=data.get("bucketStart", ""),
            completed=int(data.get("completed", 0)),
            cancelled=int(data.get("cancelled", 0)),
            avg_wait_ms=_float_or_none(data.get("avgWaitMs")),
            p50_wait_ms=_float_or_none(data.get("p50WaitMs")),
            p95_wait_ms=_float_or_none(data.get("p95WaitMs")),
            avg_handle_ms=_float_or_none(data.get("avgHandleMs")),
        )


@dataclass
class StatsTimeseriesResult:
    from_: str
    to: str
    bucket: str
    buckets: list[StatsTimeseriesBucket] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> StatsTimeseriesResult:
        return cls(
            from_=data.get("from", ""),
            to=data.get("to", ""),
            bucket=data.get("bucket", ""),
            buckets=_each(data, "buckets", StatsTimeseriesBucket.from_json),
        )


@dataclass
class WorkerProductivity:
    """One worker's window report: what they finished, how they responded, and how long they worked.

    Three sources merged server-side — the task archive (throughput and handle
    times), the synced day counters (offers/accepts/rejections), and the shift
    log (`on_shift_ms` less overlapping breaks = `working_ms`). Measured facts,
    never a score.
    """

    worker_id: str
    completed: int
    cancelled: int
    avg_wait_ms: float | None = None
    avg_handle_ms: float | None = None
    p50_handle_ms: float | None = None
    p95_handle_ms: float | None = None
    offered: int = 0
    accepted: int = 0
    rejected: int = 0
    failed: int = 0
    expired: int = 0
    released: int = 0
    #: accepted / offered over the window; None before any offer, never 0.
    acceptance_rate: float | None = None
    on_shift_ms: int = 0
    break_ms: int = 0
    working_ms: int = 0
    shift_count: int = 0
    #: Handle time ÷ worked time. May exceed 1 for overlapping tasks.
    utilization: float | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerProductivity:
        return cls(
            worker_id=data.get("workerId", ""),
            completed=int(data.get("completed", 0)),
            cancelled=int(data.get("cancelled", 0)),
            avg_wait_ms=_float_or_none(data.get("avgWaitMs")),
            avg_handle_ms=_float_or_none(data.get("avgHandleMs")),
            p50_handle_ms=_float_or_none(data.get("p50HandleMs")),
            p95_handle_ms=_float_or_none(data.get("p95HandleMs")),
            offered=int(data.get("offered", 0)),
            accepted=int(data.get("accepted", 0)),
            rejected=int(data.get("rejected", 0)),
            failed=int(data.get("failed", 0)),
            expired=int(data.get("expired", 0)),
            released=int(data.get("released", 0)),
            acceptance_rate=_float_or_none(data.get("acceptanceRate")),
            on_shift_ms=int(data.get("onShiftMs", 0)),
            break_ms=int(data.get("breakMs", 0)),
            working_ms=int(data.get("workingMs", 0)),
            shift_count=int(data.get("shiftCount", 0)),
            utilization=_float_or_none(data.get("utilization")),
        )


@dataclass
class WorkerStatsResult:
    from_: str
    to: str
    workers: list[WorkerProductivity] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerStatsResult:
        return cls(
            from_=data.get("from", ""),
            to=data.get("to", ""),
            workers=_each(data, "workers", WorkerProductivity.from_json),
        )


@dataclass
class WorkerStatsQuery:
    """Window plus an optional crew filter for the per-worker productivity report."""

    from_: str | None = None  # ISO-8601
    to: str | None = None  # ISO-8601
    bucket: str | None = None  # 'hour' | 'day'
    team_id: str | None = None

    def to_params(self) -> dict[str, str]:
        return params({"from": self.from_, "to": self.to, "bucket": self.bucket, "teamId": self.team_id})


@dataclass
class WorkerTimeseriesBucket(StatsTimeseriesBucket):
    """One bucket of a single worker's series.

    Worked time and lifecycle counters are day-grained, so they are present only
    on `bucket='day'` responses — None on hour buckets rather than zero, because
    "not measured at this resolution" is not "measured as nothing".
    """

    on_shift_ms: int | None = None
    break_ms: int | None = None
    working_ms: int | None = None
    offered: int | None = None
    accepted: int | None = None
    rejected: int | None = None
    failed: int | None = None
    expired: int | None = None
    released: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTimeseriesBucket:
        return cls(
            bucket_start=data.get("bucketStart", ""),
            completed=int(data.get("completed", 0)),
            cancelled=int(data.get("cancelled", 0)),
            avg_wait_ms=_float_or_none(data.get("avgWaitMs")),
            p50_wait_ms=_float_or_none(data.get("p50WaitMs")),
            p95_wait_ms=_float_or_none(data.get("p95WaitMs")),
            avg_handle_ms=_float_or_none(data.get("avgHandleMs")),
            on_shift_ms=_int_or_none(data.get("onShiftMs")),
            break_ms=_int_or_none(data.get("breakMs")),
            working_ms=_int_or_none(data.get("workingMs")),
            offered=_int_or_none(data.get("offered")),
            accepted=_int_or_none(data.get("accepted")),
            rejected=_int_or_none(data.get("rejected")),
            failed=_int_or_none(data.get("failed")),
            expired=_int_or_none(data.get("expired")),
            released=_int_or_none(data.get("released")),
        )


@dataclass
class WorkerTimeseriesResult:
    worker_id: str
    from_: str
    to: str
    bucket: str = "hour"
    buckets: list[WorkerTimeseriesBucket] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTimeseriesResult:
        return cls(
            worker_id=data.get("workerId", ""),
            from_=data.get("from", ""),
            to=data.get("to", ""),
            bucket=data.get("bucket", "hour"),
            buckets=_each(data, "buckets", WorkerTimeseriesBucket.from_json),
        )


# ─────────────────────────────── presence & breaks ───────────────────────────────


@dataclass
class TeamPresenceMember:
    worker_id: str
    label: str
    status: str  # 'working' | 'on-break' | 'paused'
    break_started_at: str | None = None
    break_reason: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamPresenceMember:
        return cls(
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            status=data.get("status", ""),
            break_started_at=data.get("breakStartedAt"),
            break_reason=data.get("breakReason"),
        )


@dataclass
class TeamPresenceCounts:
    working: int = 0
    on_break: int = 0
    paused: int = 0
    total: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamPresenceCounts:
        return cls(
            working=int(data.get("working", 0)),
            on_break=int(data.get("onBreak", 0)),
            paused=int(data.get("paused", 0)),
            total=int(data.get("total", 0)),
        )


@dataclass
class TeamPresence:
    """Who is working vs on break vs paused."""

    workers: list[TeamPresenceMember] = field(default_factory=list)
    counts: TeamPresenceCounts = field(default_factory=TeamPresenceCounts)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamPresence:
        return cls(
            workers=_each(data, "workers", TeamPresenceMember.from_json),
            counts=TeamPresenceCounts.from_json(data.get("counts") or {}),
        )


@dataclass
class WorkerBreak:
    id: str
    started_at: str
    ended_at: str | None = None
    reason: str | None = None
    duration_ms: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerBreak:
        return cls(
            id=data.get("id", ""),
            started_at=data.get("startedAt", ""),
            ended_at=data.get("endedAt"),
            reason=data.get("reason"),
            duration_ms=int(data.get("durationMs", 0)),
        )


@dataclass
class WorkerBreakMetric:
    worker_id: str
    label: str
    count: int
    total_break_ms: int
    longest_break_ms: int
    active: bool

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerBreakMetric:
        return cls(
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            count=int(data.get("count", 0)),
            total_break_ms=int(data.get("totalBreakMs", 0)),
            longest_break_ms=int(data.get("longestBreakMs", 0)),
            active=bool(data.get("active", False)),
        )


@dataclass
class WorkspaceBreakMetrics:
    workers: list[WorkerBreakMetric] = field(default_factory=list)
    total_break_ms: int = 0
    break_count: int = 0
    active_count: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkspaceBreakMetrics:
        return cls(
            workers=_each(data, "workers", WorkerBreakMetric.from_json),
            total_break_ms=int(data.get("totalBreakMs", 0)),
            break_count=int(data.get("breakCount", 0)),
            active_count=int(data.get("activeCount", 0)),
        )


# ─────────────────────────────── worker portal plane ───────────────────────────────


@dataclass
class WorkerLogin:
    workspace_id: str
    worker_id: str
    pin: str

    def to_json(self) -> dict[str, Any]:
        return {"workspaceId": self.workspace_id, "workerId": self.worker_id, "pin": self.pin}


@dataclass
class WorkerSessionToken:
    """A ``wt_`` bearer token scoped to exactly one worker."""

    token: str
    # ISO-8601 moment this token stops working. FivexerWorker uses it to rotate ahead of expiry;
    # absent on servers predating the refresh endpoint, which is why it is optional rather than
    # defaulted to a time — a made-up expiry would rotate either far too early or never.
    expires_at: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerSessionToken:
        return cls(token=data.get("token", ""), expires_at=data.get("expiresAt"))


@dataclass
class WorkerTaskDetail:
    """Rich detail for a task assigned to the authenticated worker (portal surface only)."""

    id: str
    status: str
    tags: list[str] = field(default_factory=list)
    priority: float | None = None
    title: str | None = None
    description: str | None = None
    context: dict[str, Any] | None = None
    references: list[TaskReference] | None = None
    created_at: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTaskDetail:
        return cls(
            id=data["id"],
            status=data.get("status", ""),
            tags=list(data.get("tags") or []),
            priority=data.get("priority"),
            title=data.get("title"),
            description=data.get("description"),
            context=data.get("context"),
            references=_each_or_none(data, "references", TaskReference.from_json),
            created_at=data.get("createdAt"),
        )


@dataclass
class WorkerBreakStarted:
    worker_id: str
    since: str
    on_break: bool = True

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerBreakStarted:
        return cls(
            worker_id=data.get("workerId", ""),
            since=data.get("since", ""),
            on_break=bool(data.get("onBreak", True)),
        )


@dataclass
class WorkerBreakEnded:
    worker_id: str
    ended_at: str
    on_break: bool = False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerBreakEnded:
        return cls(
            worker_id=data.get("workerId", ""),
            ended_at=data.get("endedAt", ""),
            on_break=bool(data.get("onBreak", False)),
        )


@dataclass
class WorkerBreakToday:
    worker_id: str
    since: str
    breaks: list[WorkerBreak] = field(default_factory=list)
    # The open break, if one is running right now
    active: WorkerBreak | None = None
    completed_tasks: int = 0
    total_break_ms: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerBreakToday:
        active = data.get("active")
        return cls(
            worker_id=data.get("workerId", ""),
            since=data.get("since", ""),
            breaks=_each(data, "breaks", WorkerBreak.from_json),
            active=None if active is None else WorkerBreak.from_json(active),
            completed_tasks=int(data.get("completedTasks", 0)),
            total_break_ms=int(data.get("totalBreakMs", 0)),
        )


@dataclass
class WorkerMetricsToday:
    worker_id: str
    since: str
    completed_tasks: int = 0
    break_count: int = 0
    total_break_ms: int = 0
    longest_break_ms: int = 0
    #: Real worked time (shift minus breaks) once the shift log has today's
    #: rows; older servers report the since-midnight approximation instead.
    working_ms: int = 0
    #: Additive — absent on servers predating the shift log.
    on_shift_ms: int = 0
    shift_count: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetricsToday:
        return cls(
            worker_id=data.get("workerId", ""),
            since=data.get("since", ""),
            completed_tasks=int(data.get("completedTasks", 0)),
            break_count=int(data.get("breakCount", 0)),
            total_break_ms=int(data.get("totalBreakMs", 0)),
            longest_break_ms=int(data.get("longestBreakMs", 0)),
            working_ms=int(data.get("workingMs", 0)),
            on_shift_ms=int(data.get("onShiftMs", 0)),
            shift_count=int(data.get("shiftCount", 0)),
        )


# ─────────────────────────────── quota ───────────────────────────────

# ─────────────────────────────── supervisor plane ───────────────────────────────
#
# A third credential type alongside the workspace key and the worker token. A supervisor
# watches and unblocks work rather than doing it: they can unpark, reprioritise, hand a task
# to a crew member and pause one, but never create work or manage the roster. Scope is either
# one team or the whole workspace, and every action is checked against it server-side.


@dataclass
class AcceptSupervisorInvite:
    token: str  # single-use, from the `?token=` of the supervisor link

    def to_json(self) -> dict[str, Any]:
        return {"token": self.token}


@dataclass
class SupervisorIdentity:
    id: str
    label: str = ""
    email: str | None = None
    team_id: str | None = None  # None for a workspace-wide supervisor

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorIdentity:
        return cls(
            id=data["id"],
            label=data.get("label", ""),
            email=data.get("email"),
            team_id=data.get("teamId"),
        )


@dataclass
class SupervisorSession:
    """``expires_at`` is **epoch-milliseconds**, not the ISO-8601 string the worker plane
    sends. The two planes genuinely differ on the wire; this mirrors the server rather than
    papering over it."""

    token: str  # an `sv_` supervisor session token
    expires_at: int  # epoch-ms
    supervisor: SupervisorIdentity
    workspace_id: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorSession:
        return cls(
            token=data["token"],
            expires_at=int(data.get("expiresAt", 0)),
            supervisor=SupervisorIdentity.from_json(data.get("supervisor") or {"id": ""}),
            workspace_id=data.get("workspaceId", ""),
        )


@dataclass
class SupervisorEntry:
    """Where a supervisor can sign in from. Unauthenticated, and deliberately takes no
    workspace id — echoing one back would turn it into an existence oracle."""

    console_url: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorEntry:
        return cls(console_url=data.get("consoleUrl"))


@dataclass
class SupervisorMe:
    supervisor_id: str
    label: str = ""
    email: str | None = None
    workspace_id: str = ""
    #: ``None`` is a real answer — it means the whole workspace, not a missing value.
    team_key: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorMe:
        return cls(
            supervisor_id=data.get("supervisorId", ""),
            label=data.get("label", ""),
            email=data.get("email"),
            workspace_id=data.get("workspaceId", ""),
            team_key=data.get("teamKey"),
        )


@dataclass
class SupervisorCounts:
    queued: int = 0
    pending: int = 0
    parked: int = 0
    oldest_wait_ms: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorCounts:
        return cls(
            queued=int(data.get("queued", 0)),
            pending=int(data.get("pending", 0)),
            parked=int(data.get("parked", 0)),
            oldest_wait_ms=int(data.get("oldestWaitMs", 0)),
        )


@dataclass
class SupervisorCrewMember:
    worker_id: str
    backlog: int = 0
    available: bool = False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorCrewMember:
        return cls(
            worker_id=data.get("workerId", ""),
            backlog=int(data.get("backlog", 0)),
            available=bool(data.get("available", False)),
        )


@dataclass
class SupervisorParkedTask:
    """A parked task as the board shows it — enough to decide on, not the whole task."""

    id: str
    tags: list[str] = field(default_factory=list)
    priority: float | None = None
    created_at: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorParkedTask:
        return cls(
            id=data["id"],
            tags=list(data.get("tags") or []),
            priority=_float_or_none(data.get("priority")),
            created_at=_int_or_none(data.get("createdAt")),
        )


@dataclass
class SupervisorOverview:
    """The whole board in one response: counts, crew and what needs attention.

    Deliberately one endpoint rather than four — this is a phone on a depot floor, and four
    round trips over a bad connection show a board that assembles itself in pieces. ``parked``
    is capped at 50 server-side for the same reason.
    """

    team_key: str | None = None
    counts: SupervisorCounts = field(default_factory=SupervisorCounts)
    #: Busiest first.
    crew: list[SupervisorCrewMember] = field(default_factory=list)
    parked: list[SupervisorParkedTask] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorOverview:
        return cls(
            team_key=data.get("teamKey"),
            counts=SupervisorCounts.from_json(data.get("counts") or {}),
            crew=_each(data, "crew", SupervisorCrewMember.from_json),
            parked=_each(data, "parked", SupervisorParkedTask.from_json),
        )


@dataclass
class SupervisorAssignResult:
    id: str
    worker_id: str = ""
    status: str = "pending"

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorAssignResult:
        return cls(
            id=data.get("id", ""),
            worker_id=data.get("workerId", ""),
            status=data.get("status", "pending"),
        )


@dataclass
class SupervisorAvailabilityResult:
    worker_id: str
    available: bool = False
    #: Non-empty only when ``release_backlog`` was set; accepted work never moves.
    released_task_ids: list[str] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SupervisorAvailabilityResult:
        return cls(
            worker_id=data.get("workerId", ""),
            available=bool(data.get("available", False)),
            released_task_ids=list(data.get("releasedTaskIds") or []),
        )


# ─────────────────────────────── worker portal: self-service ───────────────────────────────


@dataclass
class WorkerMe:
    """Who am I, and am I on shift? Works on data-plane-only deployments, where the break and
    team endpoints 501 — ``on_break`` is simply always false there."""

    worker_id: str
    label: str = ""
    available: bool = False
    pending_approval: bool = False
    on_break: bool = False
    break_started_at: str | None = None
    skills: list[WorkerSkill] = field(default_factory=list)
    skill_setup_pending: bool = False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMe:
        return cls(
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            available=bool(data.get("available", False)),
            pending_approval=bool(data.get("pendingApproval", False)),
            on_break=bool(data.get("onBreak", False)),
            break_started_at=data.get("breakStartedAt"),
            skills=_each(data, "skills", WorkerSkill.from_json),
            skill_setup_pending=bool(data.get("skillSetupPending", False)),
        )


@dataclass
class WorkerAvailabilityState:
    worker_id: str
    available: bool

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerAvailabilityState:
        return cls(worker_id=data.get("workerId", ""), available=bool(data.get("available", False)))


@dataclass
class WorkerSkillLevel:
    """One claimed skill. Levels are 1–5; the routing weight they project to is the server's
    business, and an operator's weight override survives a skill kept here."""

    skill_id: str
    level: int

    def to_json(self) -> dict[str, Any]:
        return {"skillId": self.skill_id, "level": self.level}


@dataclass
class WorkerSkillSet:
    worker_id: str
    skills: list[WorkerSkill] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerSkillSet:
        return cls(
            worker_id=data.get("workerId", ""),
            skills=_each(data, "skills", WorkerSkill.from_json),
        )


@dataclass
class ChangePin:
    current_pin: str
    new_pin: str  # 4–256 chars

    def to_json(self) -> dict[str, Any]:
        return {"currentPin": self.current_pin, "newPin": self.new_pin}


@dataclass
class WorkerMetricsWindowDay:
    day: str
    completed: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetricsWindowDay:
        return cls(day=data.get("day", ""), completed=int(data.get("completed", 0)))


@dataclass
class WorkerMetricsWindow:
    worker_id: str
    since: str = ""
    days: list[WorkerMetricsWindowDay] = field(default_factory=list)
    median_wait_ms: int | None = None
    median_cycle_ms: int | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetricsWindow:
        return cls(
            worker_id=data.get("workerId", ""),
            since=data.get("since", ""),
            days=_each(data, "days", WorkerMetricsWindowDay.from_json),
            median_wait_ms=_int_or_none(data.get("medianWaitMs")),
            median_cycle_ms=_int_or_none(data.get("medianCycleMs")),
        )


@dataclass
class WorkerMetricsDay:
    """Today's recorded time and throughput for one worker (operator plane)."""

    since: str = ""
    completed_tasks: int = 0
    shift_count: int = 0
    on_shift_ms: int = 0
    break_count: int = 0
    total_break_ms: int = 0
    longest_break_ms: int = 0
    working_ms: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetricsDay:
        return cls(
            since=data.get("since", ""),
            completed_tasks=int(data.get("completedTasks", 0)),
            shift_count=int(data.get("shiftCount", 0)),
            on_shift_ms=int(data.get("onShiftMs", 0)),
            break_count=int(data.get("breakCount", 0)),
            total_break_ms=int(data.get("totalBreakMs", 0)),
            longest_break_ms=int(data.get("longestBreakMs", 0)),
            working_ms=int(data.get("workingMs", 0)),
        )


@dataclass
class WorkerMetricsPeriod:
    """A worker's rolling window: recorded time, throughput and response counters."""

    from_: str = ""
    to: str = ""
    #: None when the deployment keeps no task history at all — distinct from an
    #: empty list, which means "history exists and this worker finished nothing".
    #: The other ports preserve the same distinction.
    days: list[WorkerMetricsWindowDay] | None = None
    median_wait_ms: int | None = None
    median_cycle_ms: int | None = None
    shift_count: int = 0
    on_shift_ms: int = 0
    break_ms: int = 0
    working_ms: int = 0
    offered: int = 0
    accepted: int = 0
    rejected: int = 0
    completed: int = 0
    failed: int = 0
    expired: int = 0
    released: int = 0
    #: None before any offer, never 0 — a rate over nothing is not zero.
    acceptance_rate: float | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetricsPeriod:
        return cls(
            from_=data.get("from", ""),
            to=data.get("to", ""),
            days=(None if data.get("days") is None else _each(data, "days", WorkerMetricsWindowDay.from_json)),
            median_wait_ms=_int_or_none(data.get("medianWaitMs")),
            median_cycle_ms=_int_or_none(data.get("medianCycleMs")),
            shift_count=int(data.get("shiftCount", 0)),
            on_shift_ms=int(data.get("onShiftMs", 0)),
            break_ms=int(data.get("breakMs", 0)),
            working_ms=int(data.get("workingMs", 0)),
            offered=int(data.get("offered", 0)),
            accepted=int(data.get("accepted", 0)),
            rejected=int(data.get("rejected", 0)),
            completed=int(data.get("completed", 0)),
            failed=int(data.get("failed", 0)),
            expired=int(data.get("expired", 0)),
            released=int(data.get("released", 0)),
            acceptance_rate=_float_or_none(data.get("acceptanceRate")),
        )


@dataclass
class WorkerMetrics:
    """One worker's recorded time and throughput: today, plus a rolling window.

    The operator-plane mirror of what the worker sees in their own portal —
    same numbers on both planes, deliberately.
    """

    worker_id: str
    today: WorkerMetricsDay = field(default_factory=WorkerMetricsDay)
    window: WorkerMetricsPeriod = field(default_factory=WorkerMetricsPeriod)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerMetrics:
        return cls(
            worker_id=data.get("workerId", ""),
            today=WorkerMetricsDay.from_json(data.get("today") or {}),
            window=WorkerMetricsPeriod.from_json(data.get("window") or {}),
        )


@dataclass
class WorkerTimeEntry:
    """One recorded stretch of a worker's time — a shift, or a break inside one.

    `end_reason` 'timeout' means the platform clocked out an unattended worker
    that went silent past its declared liveness contract.
    """

    type: str  # 'shift' | 'break'
    started_at: str = ""
    ended_at: str | None = None
    duration_ms: int = 0
    #: Shifts only: 'portal' | 'operator' | 'supervisor'.
    source: str | None = None
    #: Shifts only: 'manual' | 'timeout' | 'removed'; None while still open.
    end_reason: str | None = None
    #: Breaks only: the worker's stated reason.
    reason: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTimeEntry:
        return cls(
            type=data.get("type", ""),
            started_at=data.get("startedAt", ""),
            ended_at=data.get("endedAt"),
            duration_ms=int(data.get("durationMs", 0)),
            source=data.get("source"),
            end_reason=data.get("endReason"),
            reason=data.get("reason"),
        )


@dataclass
class WorkerTimeTotals:
    shift_count: int = 0
    on_shift_ms: int = 0
    break_count: int = 0
    break_ms: int = 0
    #: on_shift_ms − break_ms: breaks are time inside a shift.
    working_ms: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTimeTotals:
        return cls(
            shift_count=int(data.get("shiftCount", 0)),
            on_shift_ms=int(data.get("onShiftMs", 0)),
            break_count=int(data.get("breakCount", 0)),
            break_ms=int(data.get("breakMs", 0)),
            working_ms=int(data.get("workingMs", 0)),
        )


@dataclass
class WorkerTimeEntriesResult:
    """The recorded shift and break log for a window — the working-time record."""

    worker_id: str
    from_: str = ""
    to: str = ""
    entries: list[WorkerTimeEntry] = field(default_factory=list)
    totals: WorkerTimeTotals = field(default_factory=WorkerTimeTotals)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerTimeEntriesResult:
        return cls(
            worker_id=data.get("workerId", ""),
            from_=data.get("from", ""),
            to=data.get("to", ""),
            entries=_each(data, "entries", WorkerTimeEntry.from_json),
            totals=WorkerTimeTotals.from_json(data.get("totals") or {}),
        )


@dataclass
class WorkerLocation:
    latitude: float
    longitude: float

    def to_json(self) -> dict[str, Any]:
        return {"latitude": self.latitude, "longitude": self.longitude}


@dataclass
class WorkerLocationResult:
    worker_id: str
    latitude: float
    longitude: float

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerLocationResult:
        return cls(
            worker_id=data.get("workerId", ""),
            latitude=float(data.get("latitude", 0)),
            longitude=float(data.get("longitude", 0)),
        )


@dataclass
class WorkerDeviceInput:
    """An Expo push token from the native app. Web browsers use
    :class:`PushSubscriptionInput` instead — a deployment may have either, both, or neither."""

    token: str
    platform: str = "unknown"  # 'ios' | 'android' | 'unknown'

    def to_json(self) -> dict[str, Any]:
        return {"token": self.token, "platform": self.platform}


@dataclass
class WorkerDevice:
    token: str
    platform: str = "unknown"
    created_at: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerDevice:
        return cls(
            token=data.get("token", ""),
            platform=data.get("platform", "unknown"),
            created_at=data.get("createdAt"),
        )


@dataclass
class PushConfig:
    """Whether this deployment can send Web Push, and the VAPID key to subscribe with.

    ``enabled=False`` is a deployment fact, not an error: the portal still works, it just cannot
    push. Check it before prompting for notification permission — a prompt the deployment cannot
    honour is worse than no prompt.
    """

    enabled: bool = False
    public_key: str | None = None  # None exactly when `enabled` is False

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> PushConfig:
        return cls(enabled=bool(data.get("enabled", False)), public_key=data.get("publicKey"))


@dataclass
class PushSubscriptionInput:
    """A browser Web Push subscription, shaped as ``PushSubscription.toJSON()`` returns it."""

    endpoint: str
    p256dh: str
    auth: str

    def to_json(self) -> dict[str, Any]:
        return {"endpoint": self.endpoint, "keys": {"p256dh": self.p256dh, "auth": self.auth}}


@dataclass
class PushSubscription:
    endpoint: str
    created_at: str = ""

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> PushSubscription:
        return cls(endpoint=data.get("endpoint", ""), created_at=data.get("createdAt", ""))


@dataclass
class JoinWorkspace:
    token: str
    name: str
    pin: str  # 4–256 chars
    email: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"email": self.email})
        payload.update({"token": self.token, "name": self.name, "pin": self.pin})
        return payload


@dataclass
class JoinWorkspaceResult:
    """What a worker gets back after self-registering through a QR join link. ``worker_id`` is
    server-generated — show it to them, it is their PIN-login username."""

    token: str  # a `wt_` worker session token — they are signed in already
    workspace_id: str
    worker_id: str
    label: str = ""
    expires_at: str | None = None
    pending_approval: bool = False
    portal_url: str = ""

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> JoinWorkspaceResult:
        return cls(
            token=data["token"],
            workspace_id=data.get("workspaceId", ""),
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            expires_at=data.get("expiresAt"),
            pending_approval=bool(data.get("pendingApproval", False)),
            portal_url=data.get("portalUrl", ""),
        )


@dataclass
class AcceptWorkerInvite:
    token: str  # single-use, from the `?token=` of the emailed link
    pin: str  # 4–256 chars

    def to_json(self) -> dict[str, Any]:
        return {"token": self.token, "pin": self.pin}


@dataclass
class AcceptWorkerInviteResult:
    """Unlike :class:`JoinWorkspaceResult` the worker id already existed — an operator created the
    identity when they sent the invite — so nothing here is server-generated."""

    token: str
    workspace_id: str
    worker_id: str
    label: str = ""
    expires_at: str | None = None
    portal_url: str = ""

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> AcceptWorkerInviteResult:
        return cls(
            token=data["token"],
            workspace_id=data.get("workspaceId", ""),
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            expires_at=data.get("expiresAt"),
            portal_url=data.get("portalUrl", ""),
        )


# ─────────────────────────────── bulk create & dry-run check ───────────────────────────────


@dataclass
class BulkTaskError:
    code: str
    message: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> BulkTaskError:
        return cls(code=data.get("code", ""), message=data.get("message", ""))


@dataclass
class BulkTaskResult:
    """One entry of a bulk create. The wire sends a union — either an id or an error — so
    exactly one of ``id``/``error`` is ever set; :attr:`ok` is the discriminator."""

    index: int
    id: str | None = None
    status: str | None = None
    error: BulkTaskError | None = None

    @property
    def ok(self) -> bool:
        return self.error is None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> BulkTaskResult:
        raw_error = data.get("error")
        return cls(
            index=int(data.get("index", 0)),
            id=data.get("id"),
            status=data.get("status"),
            error=None if raw_error is None else BulkTaskError.from_json(raw_error),
        )


@dataclass
class BulkTaskReport:
    created: int
    failed: int
    results: list[BulkTaskResult] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> BulkTaskReport:
        return cls(
            created=int(data.get("created", 0)),
            failed=int(data.get("failed", 0)),
            results=_each(data, "results", BulkTaskResult.from_json),
        )


@dataclass
class TaskCheckIssue:
    severity: str  # 'error' | 'warning' | 'info'
    code: str
    message: str
    tag: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskCheckIssue:
        return cls(
            severity=data.get("severity", ""),
            code=data.get("code", ""),
            message=data.get("message", ""),
            tag=data.get("tag"),
        )


@dataclass
class TaskCheckReport:
    """A dry run: what *would* happen to this task. Creates and reserves nothing."""

    eligible_worker_count: int
    evaluated_at: int  # epoch-ms
    issues: list[TaskCheckIssue] = field(default_factory=list)
    uncovered_tags: list[str] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskCheckReport:
        return cls(
            eligible_worker_count=int(data.get("eligibleWorkerCount", 0)),
            evaluated_at=int(data.get("evaluatedAt", 0)),
            issues=_each(data, "issues", TaskCheckIssue.from_json),
            uncovered_tags=list(data.get("uncoveredTags") or []),
        )


@dataclass
class TaskEscalation:
    id: str
    escalated: bool
    parked: bool
    escalation_level: int

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskEscalation:
        return cls(
            id=data.get("id", ""),
            escalated=bool(data.get("escalated", False)),
            parked=bool(data.get("parked", False)),
            escalation_level=int(data.get("escalationLevel", 0)),
        )


@dataclass
class TaskList:
    """An unpaginated task listing — the parked and scheduled views return the whole set."""

    tasks: list[Task] = field(default_factory=list)
    count: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TaskList:
        return cls(tasks=_each(data, "tasks", Task.from_json), count=int(data.get("count", 0)))


@dataclass
class UnparkTask:
    """Which clocks to reset when returning a parked task to the queue. Leave a clock set and
    the next sweep may park the task straight back."""

    reset_escalation: bool | None = None
    reset_sla: bool | None = None
    reset_schedule: bool | None = None

    def to_json(self) -> dict[str, Any]:
        return compact(
            {
                "resetEscalation": self.reset_escalation,
                "resetSla": self.reset_sla,
                "resetSchedule": self.reset_schedule,
            }
        )


# ─────────────────────────────── teams ───────────────────────────────


@dataclass
class Team:
    id: str
    key: str
    tag: str
    name: str
    description: str | None = None
    color: str | None = None
    created_at: str = ""  # ISO-8601

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> Team:
        return cls(
            id=data["id"],
            key=data.get("key", ""),
            tag=data.get("tag", ""),
            name=data.get("name", ""),
            description=data.get("description"),
            color=data.get("color"),
            created_at=data.get("createdAt", ""),
        )


@dataclass
class UpsertTeam:
    key: str
    name: str
    description: str | None = None
    color: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"description": self.description, "color": self.color})
        payload.update({"key": self.key, "name": self.name})
        return payload


@dataclass
class PatchTeam:
    """Partial update — absent fields keep their stored value. ``key`` is immutable."""

    name: str | None = None
    description: str | None = None
    color: str | None = None

    def to_json(self) -> dict[str, Any]:
        return compact({"name": self.name, "description": self.description, "color": self.color})


@dataclass
class TeamMember:
    worker_id: str
    role: str = "member"  # 'member' | 'lead'
    added_at: str = ""  # ISO-8601

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamMember:
        return cls(
            worker_id=data.get("workerId", ""),
            role=data.get("role", "member"),
            added_at=data.get("addedAt", ""),
        )


@dataclass
class TeamMembers:
    team_id: str
    members: list[TeamMember] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamMembers:
        return cls(
            team_id=data.get("teamId", ""),
            members=_each(data, "members", TeamMember.from_json),
        )


@dataclass
class TeamRoster:
    """The result of replacing a team's membership wholesale."""

    team_id: str
    worker_ids: list[str] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> TeamRoster:
        return cls(team_id=data.get("teamId", ""), worker_ids=list(data.get("workerIds") or []))


# ─────────────────────────────── join links ───────────────────────────────


@dataclass
class JoinLink:
    id: str
    label: str
    status: str = "active"  # 'active' | 'revoked' | 'expired' | 'exhausted'
    team_id: str | None = None
    tags: list[str] = field(default_factory=list)
    requires_approval: bool = False
    max_uses: int | None = None
    use_count: int = 0
    created_at: str = ""
    expires_at: str = ""
    revoked_at: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> JoinLink:
        return cls(
            id=data["id"],
            label=data.get("label", ""),
            status=data.get("status", "active"),
            team_id=data.get("teamId"),
            tags=list(data.get("tags") or []),
            requires_approval=bool(data.get("requiresApproval", False)),
            max_uses=_int_or_none(data.get("maxUses")),
            use_count=int(data.get("useCount", 0)),
            created_at=data.get("createdAt", ""),
            expires_at=data.get("expiresAt", ""),
            revoked_at=data.get("revokedAt"),
        )


@dataclass
class CreateJoinLink:
    label: str
    team_id: str | None = None
    tags: list[str] | None = None
    skills: list[WorkerSkillAssignment] | None = None
    requires_approval: bool | None = None
    max_uses: int | None = None
    expires_in_ms: int | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "teamId": self.team_id,
                "tags": self.tags,
                "skills": None if self.skills is None else [s.to_json() for s in self.skills],
                "requiresApproval": self.requires_approval,
                "maxUses": self.max_uses,
                "expiresInMs": self.expires_in_ms,
            }
        )
        payload["label"] = self.label
        return payload


@dataclass
class CreateJoinLinkResult:
    """``join_url`` is returned only here — a lost link is re-created, never recovered."""

    link: JoinLink
    join_url: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> CreateJoinLinkResult:
        return cls(link=JoinLink.from_json(data["link"]), join_url=data.get("joinUrl", ""))


# ─────────────────────────────── worker identities & invites ───────────────────────────────


@dataclass
class PublicWorkerIdentity:
    """A worker's portal credential. Never carries the PIN — only whether one is set."""

    id: str
    worker_id: str
    label: str
    status: str = "active"  # 'active' | 'revoked'
    email: str | None = None
    has_pin: bool = False
    activated_at: str | None = None
    created_at: str = ""
    revoked_at: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> PublicWorkerIdentity:
        return cls(
            id=data["id"],
            worker_id=data.get("workerId", ""),
            label=data.get("label", ""),
            status=data.get("status", "active"),
            email=data.get("email"),
            has_pin=bool(data.get("hasPin", False)),
            activated_at=data.get("activatedAt"),
            created_at=data.get("createdAt", ""),
            revoked_at=data.get("revokedAt"),
        )


@dataclass
class InviteWorkerIdentity:
    email: str
    worker_id: str | None = None
    label: str | None = None
    tags: list[str] | None = None
    skills: list[WorkerSkillAssignment] | None = None
    team_ids: list[str] | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact(
            {
                "workerId": self.worker_id,
                "label": self.label,
                "tags": self.tags,
                "skills": None if self.skills is None else [s.to_json() for s in self.skills],
                "teamIds": self.team_ids,
            }
        )
        payload["email"] = self.email
        return payload


@dataclass
class WorkerInviteResult:
    """``invite_url`` is credential-equivalent until consumed: whoever holds it can set the PIN
    and act as that worker. It is returned anyway because ``email_status`` is often
    ``mailer_unconfigured``, which leaves sharing the link as the only path."""

    identity: PublicWorkerIdentity
    worker_created: bool
    email_status: str  # 'sent' | 'mailer_unconfigured' | 'send_failed'
    invite_url: str

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerInviteResult:
        return cls(
            identity=PublicWorkerIdentity.from_json(data["identity"]),
            worker_created=bool(data.get("workerCreated", False)),
            email_status=data.get("emailStatus", ""),
            invite_url=data.get("inviteUrl", ""),
        )


@dataclass
class CreateWorkerIdentity:
    label: str
    pin: str
    email: str | None = None

    def to_json(self) -> dict[str, Any]:
        payload = compact({"email": self.email})
        payload.update({"label": self.label, "pin": self.pin})
        return payload


@dataclass
class UpdateWorkerIdentity:
    """Partial update. ``email`` is explicitly nullable — pass ``None`` to leave it unchanged and
    use :data:`CLEAR` to erase it, since absent and null differ on this endpoint."""

    label: str | None = None
    email: str | None = None
    pin: str | None = None
    status: str | None = None  # 'active' | 'revoked'

    def to_json(self) -> dict[str, Any]:
        payload = compact({"label": self.label, "pin": self.pin, "status": self.status, "email": self.email})
        if self.email is CLEAR:
            payload["email"] = None
        return payload


@dataclass
class WorkerIdentityResult:
    identity: PublicWorkerIdentity

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerIdentityResult:
        return cls(identity=PublicWorkerIdentity.from_json(data["identity"]))


# ─────────────────────────────── SLA & queue audit ───────────────────────────────


@dataclass
class SlaStats:
    """SLO counters. ``acceptance_rate`` is ``None`` until at least one offer is recorded —
    a rate of 0.0 and "no data yet" are different answers."""

    tag: str | None = None
    offers: int = 0
    accepted_in_time: int = 0
    acceptance_breaches: int = 0
    completion_breaches: int = 0
    ttl_expiries: int = 0
    rejection_parked: int = 0
    schedule_misses: int = 0
    mean_accept_latency_ms: float = 0.0
    mean_complete_latency_ms: float = 0.0
    acceptance_rate: float | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> SlaStats:
        return cls(
            tag=data.get("tag"),
            offers=int(data.get("offers", 0)),
            accepted_in_time=int(data.get("acceptedInTime", 0)),
            acceptance_breaches=int(data.get("acceptanceBreaches", 0)),
            completion_breaches=int(data.get("completionBreaches", 0)),
            ttl_expiries=int(data.get("ttlExpiries", 0)),
            rejection_parked=int(data.get("rejectionParked", 0)),
            schedule_misses=int(data.get("scheduleMisses", 0)),
            mean_accept_latency_ms=float(data.get("meanAcceptLatencyMs", 0)),
            mean_complete_latency_ms=float(data.get("meanCompleteLatencyMs", 0)),
            acceptance_rate=_float_or_none(data.get("acceptanceRate")),
        )


@dataclass
class QueueAuditEntry:
    task_id: str
    tags: list[str] = field(default_factory=list)
    waiting_ms: int | None = None
    eligible_worker_count: int = 0
    uncovered_tags: list[str] = field(default_factory=list)
    blockers: dict[str, int] = field(default_factory=dict)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> QueueAuditEntry:
        return cls(
            task_id=data.get("taskId", ""),
            tags=list(data.get("tags") or []),
            waiting_ms=_int_or_none(data.get("waitingMs")),
            eligible_worker_count=int(data.get("eligibleWorkerCount", 0)),
            uncovered_tags=list(data.get("uncoveredTags") or []),
            blockers={k: int(v) for k, v in (data.get("blockers") or {}).items()},
        )


@dataclass
class QueueAuditSweepBacklog:
    schedule_activations: int = 0
    schedule_misses: int = 0
    response_deadlines: int = 0
    completion_deadlines: int = 0
    sla_expiries: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> QueueAuditSweepBacklog:
        return cls(
            schedule_activations=int(data.get("scheduleActivations", 0)),
            schedule_misses=int(data.get("scheduleMisses", 0)),
            response_deadlines=int(data.get("responseDeadlines", 0)),
            completion_deadlines=int(data.get("completionDeadlines", 0)),
            sla_expiries=int(data.get("slaExpiries", 0)),
        )


@dataclass
class QueueAuditReport:
    """Why the queue is not draining. Walks the queue against the live roster, so it is heavier
    than :meth:`Fivexer.stats` — poll it on the cadence of a dashboard, not a request."""

    evaluated_at: int = 0  # epoch-ms
    scanned: int = 0
    entries: list[QueueAuditEntry] = field(default_factory=list)
    sweep_backlog: QueueAuditSweepBacklog = field(default_factory=QueueAuditSweepBacklog)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> QueueAuditReport:
        return cls(
            evaluated_at=int(data.get("evaluatedAt", 0)),
            scanned=int(data.get("scanned", 0)),
            entries=_each(data, "entries", QueueAuditEntry.from_json),
            sweep_backlog=QueueAuditSweepBacklog.from_json(data.get("sweepBacklog") or {}),
        )


@dataclass
class QueueAuditQuery:
    limit: int | None = None
    min_waiting_ms: int | None = None
    include_healthy: bool | None = None

    def to_params(self) -> dict[str, str]:
        return params(
            {
                "limit": self.limit,
                "minWaitingMs": self.min_waiting_ms,
                # Fastify parses booleans from the string form, so `True` must not
                # reach the wire as Python's capitalised repr.
                "includeHealthy": None if self.include_healthy is None else str(self.include_healthy).lower(),
            }
        )


@dataclass
class RevertedWeights:
    reverted: list[str] = field(default_factory=list)
    count: int = 0

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> RevertedWeights:
        return cls(reverted=list(data.get("reverted") or []), count=int(data.get("count", 0)))


# ─────────────────────────────── worker portal link ───────────────────────────────


@dataclass
class WorkerPortalLink:
    """Where workers log in, and whether the portal is switched on at all."""

    portal_enabled: bool = False
    portal_url: str = ""
    exists: bool = False
    version: int | None = None
    template: str | None = None
    published_at: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> WorkerPortalLink:
        return cls(
            portal_enabled=bool(data.get("portalEnabled", False)),
            portal_url=data.get("portalUrl", ""),
            exists=bool(data.get("exists", False)),
            version=_int_or_none(data.get("version")),
            template=data.get("template"),
            published_at=data.get("publishedAt"),
        )


_QUOTA_HEADERS = {
    "task_rate_limit": "x-quota-task-rate-limit",
    "task_rate_remaining": "x-quota-task-rate-remaining",
    "queued_tasks_limit": "x-quota-queued-tasks-limit",
    "queued_tasks_remaining": "x-quota-queued-tasks-remaining",
    "workers_limit": "x-quota-workers-limit",
    "workers_remaining": "x-quota-workers-remaining",
    "skills_limit": "x-quota-skills-limit",
    "skills_remaining": "x-quota-skills-remaining",
    "worker_skills_limit": "x-quota-worker-skills-limit",
    "worker_skills_remaining": "x-quota-worker-skills-remaining",
}


@dataclass
class QuotaInfo:
    """Parsed from the ``X-Quota-*`` headers of the most recent response that carried them."""

    task_rate_limit: int | None = None
    task_rate_remaining: int | None = None
    queued_tasks_limit: int | None = None
    queued_tasks_remaining: int | None = None
    workers_limit: int | None = None
    workers_remaining: int | None = None
    skills_limit: int | None = None
    skills_remaining: int | None = None
    worker_skills_limit: int | None = None
    worker_skills_remaining: int | None = None

    @classmethod
    def from_headers(cls, headers: Mapping[str, str]) -> QuotaInfo | None:
        """Case-insensitive header lookup. Returns ``None`` if no quota headers are present."""
        lower = {k.lower(): v for k, v in headers.items()}
        found = {attr: lower.get(header) for attr, header in _QUOTA_HEADERS.items()}
        if not any(value is not None for value in found.values()):
            return None
        return cls(**{attr: int(value) for attr, value in found.items() if value is not None})


@dataclass
class VoiceIceServer:
    """**Experimental — voice is not production-ready.** May change or be withdrawn in a patch
    release; do not build on it yet.

    One STUN/TURN server, shaped for the browser's ``RTCIceServer``. Unlike the console's read
    view this *does* carry ``credential``: a worker or supervisor about to place a call needs the
    TURN secret to authenticate to the relay, so the two reads differ deliberately.
    """

    # A single URL or a list of them, exactly as the browser API accepts
    urls: str | list[str]
    username: str | None = None
    credential: str | None = None

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> VoiceIceServer:
        urls = data.get("urls", "")
        return cls(
            urls=list(urls) if isinstance(urls, list) else urls,
            username=data.get("username"),
            credential=data.get("credential"),
        )


@dataclass
class VoiceIceServers:
    """**Experimental** — see :class:`VoiceIceServer`."""

    ice_servers: list[VoiceIceServer] = field(default_factory=list)

    @classmethod
    def from_json(cls, data: Mapping[str, Any]) -> VoiceIceServers:
        return cls(ice_servers=_each(data, "iceServers", VoiceIceServer.from_json))
