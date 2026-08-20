"""Fivexer /v1 client (sync ``Fivexer`` and async ``AsyncFivexer``).

Mirrors the TypeScript ``@fivexer/sdk`` ``Fivexer`` class behavior exactly:
    - Constructor: ``{base_url, api_key, timeout=30s, max_retries=1, http_client?}``
    - Every request: ``Authorization: Bearer <key>``, ``Accept: application/json``
    - ``POST`` auto-sends an ``Idempotency-Key`` (UUID) unless the caller supplies one
    - Retry on ``429``/``5xx`` honoring ``Retry-After`` (one retry by default)
    - Parse ``X-Quota-*`` on every response onto ``client.quota``; attach to errors too
    - Non-2xx -> :class:`FivexerApiError`; ``204`` -> ``None``

Each operation is described once in :mod:`fivexer._specs`; the resource classes below are thin
dispatchers over those specs, so the sync and async surfaces cannot drift apart.
"""

from __future__ import annotations

import asyncio
import builtins
import time
import uuid
from collections.abc import Mapping
from typing import Any, TypeVar

import httpx

from . import _specs as specs
from ._specs import RequestSpec
from .errors import FivexerApiError
from .models import (
    AddComment,
    AssignTaskResult,
    Attachment,
    AttachmentDownload,
    BulkTaskReport,
    Comment,
    CommentPage,
    CreateAttachment,
    CreatedAttachment,
    CreateJoinLink,
    CreateJoinLinkResult,
    CreateNotificationChannel,
    CreateNotificationSequence,
    CreateTask,
    CreateWorkerIdentity,
    Decision,
    InviteWorkerIdentity,
    JoinLink,
    LearnedWeightsPreview,
    LearningFeedbackItem,
    LearningFeedbackResult,
    LearningStatus,
    ListDecisionsQuery,
    ListRunsQuery,
    ListTasksQuery,
    NotificationChannel,
    NotificationSequence,
    PatchSkill,
    PatchTeam,
    PatchWorker,
    PublicWorkerIdentity,
    QueueAuditQuery,
    QueueAuditReport,
    QuotaInfo,
    RevertedWeights,
    SetTaskContext,
    Skill,
    SlaStats,
    StartRun,
    StatsTimeseriesResult,
    StatsWindowQuery,
    SuggestWorkers,
    SuggestWorkersResult,
    Task,
    TaskAction,
    TaskCheckReport,
    TaskContext,
    TaskEscalation,
    TaskList,
    TaskPage,
    TaskPriority,
    Team,
    TeamMembers,
    TeamPresence,
    TeamRoster,
    UnparkTask,
    UpdateNotificationChannel,
    UpdateNotificationSequence,
    UpdateWorkerIdentity,
    UpsertSkill,
    UpsertTeam,
    UpsertWorker,
    WorkerAvailability,
    WorkerDetail,
    WorkerIdentityResult,
    WorkerInviteResult,
    WorkerLearningStats,
    WorkerList,
    WorkerPortalLink,
    WorkerQueue,
    WorkerStatsResult,
    WorkflowDefinition,
    WorkflowDefinitionInput,
    WorkflowDefinitionSummary,
    WorkflowRun,
    WorkflowRunPage,
    WorkflowRunSteps,
    WorkspaceBreakMetrics,
    WorkspaceStats,
    _each,
)

T = TypeVar("T")
DEFAULT_TIMEOUT = 30.0

# Methods that are safe to retry. Task creation carries an Idempotency-Key so a retried
# POST is deduplicated server-side by the task id (or the key).
_RETRYABLE_METHODS = frozenset({"GET", "HEAD", "DELETE", "PUT", "POST"})


def _retry_after_seconds(value: str | None) -> float | None:
    """Parse a ``Retry-After`` header (seconds form only). Returns None if absent/invalid."""
    if not value:
        return None
    try:
        return max(0.0, float(value))
    except (TypeError, ValueError):
        return None


def _parse_error_body(text: str) -> dict[str, Any]:
    import json

    try:
        parsed = json.loads(text)
        if isinstance(parsed, dict):
            return parsed
    except (ValueError, TypeError):
        pass
    return {}


def _build_headers(api_key: str, body_present: bool, idempotency_key: str | None) -> dict[str, str]:
    headers = {"authorization": f"Bearer {api_key}", "accept": "application/json"}
    if idempotency_key:
        headers["idempotency-key"] = idempotency_key
    if body_present:
        headers["content-type"] = "application/json"
    return headers


def _params_to_str(params: Mapping[str, Any] | None) -> dict[str, str] | None:
    if not params:
        return None
    out: dict[str, str] = {}
    for k, v in params.items():
        if v is not None:
            out[k] = str(v)
    return out or None


def _is_retryable_status(status: int) -> bool:
    return status == 429 or status >= 500


def _to_api_error(response: httpx.Response, quota: QuotaInfo | None) -> FivexerApiError:
    body = _parse_error_body(response.text)
    err = body.get("error") if isinstance(body.get("error"), dict) else None
    code = (err or {}).get("code") or "unknown_error"
    message = (err or {}).get("message") or f"http {response.status_code}"
    retry_after = _retry_after_seconds(response.headers.get("retry-after"))
    return FivexerApiError(response.status_code, code, message, quota, retry_after)


def _upload_failed(status: int) -> FivexerApiError:
    """Object storage rejected the presigned PUT. Not a /v1 error, so it has no quota snapshot."""
    return FivexerApiError(status, "upload_failed", f"storage upload failed with http {status}", None, None)


def _size_of(payload: bytes | bytearray | memoryview) -> int:
    return len(bytes(payload))


class Fivexer:
    """Synchronous typed client for the Fivexer Platform ``/v1`` API.

    Zero hard dependencies beyond ``httpx``. Inject ``http_client`` (e.g. an
    ``httpx.Client`` with a ``MockTransport``) for testing.

    Example::

        client = Fivexer(base_url="https://api.fivexer.com", api_key="sk_test_...")
        client.workers.upsert(UpsertWorker(id="agent_1", tags=["english"]))
        task = client.tasks.create(CreateTask(tags=["english"]))
    """

    def __init__(
        self,
        base_url: str,
        api_key: str,
        *,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.Client | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        if not api_key:
            raise ValueError("api_key is required")
        self._base = base_url.rstrip("/")
        self._api_key = api_key
        self._max_retries = max_retries
        self._owns_client = http_client is None
        self._client = http_client or httpx.Client(timeout=timeout)
        self.quota: QuotaInfo | None = None
        self.tasks = _Tasks(self)
        self.workers = _Workers(self)
        self.teams = _Teams(self)
        self.join_links = _JoinLinks(self)
        self.identities = _Identities(self)
        self.skills = _Skills(self)
        self.decisions = _Decisions(self)
        self.workflows = _Workflows(self)
        self.runs = _Runs(self)
        self.learning = _Learning(self)
        self.notifications = _Notifications(self)
        self.history = _History(self)
        self.team = _Team(self)
        self.breaks = _Breaks(self)

    @property
    def base_url(self) -> str:
        return self._base

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def __enter__(self) -> Fivexer:
        return self

    def __exit__(self, *exc: Any) -> None:
        self.close()

    # -- stats (top-level, not a resource group) --
    def stats(self) -> WorkspaceStats:
        return WorkspaceStats.from_json(self._send(specs.stats()))

    def sla_stats(self, tag: str | None = None) -> SlaStats:
        """SLO counters, workspace-wide or for one tag."""
        return SlaStats.from_json(self._send(specs.stats_sla(tag)))

    def queue_audit(self, query: QueueAuditQuery | None = None) -> QueueAuditReport:
        """Why the queue is not draining. Walks the queue against the live roster, so it is
        heavier than :meth:`stats` — poll it on a dashboard's cadence, not a request's."""
        return QueueAuditReport.from_json(self._send(specs.stats_queue_audit(query)))

    def portal(self) -> WorkerPortalLink:
        """Where workers log in, and whether the portal is switched on at all."""
        return WorkerPortalLink.from_json(self._send(specs.portal_link()))

    # -- core transport --
    def _send(self, spec: RequestSpec, *, idempotency_key: str | None = None) -> Any:
        return self._request(
            spec.method, spec.path, body=spec.body, params=spec.query, idempotency_key=idempotency_key
        )

    def _request(
        self,
        method: str,
        path: str,
        *,
        body: Any = None,
        params: Mapping[str, Any] | None = None,
        idempotency_key: str | None = None,
    ) -> Any:
        url = f"{self._base}/v1{path}"
        attempt = 0
        # Stash the idempotency key: generate one for POST if not provided so a
        # transport retry is deduplicated server-side.
        key = idempotency_key
        if key is None and method == "POST":
            key = str(uuid.uuid4())
        query = _params_to_str(params)

        while True:
            headers = _build_headers(self._api_key, body is not None, key)
            response = self._client.request(
                method,
                url,
                headers=headers,
                json=body if body is not None else None,
                params=query,
            )

            quota = QuotaInfo.from_headers(dict(response.headers))
            if quota is not None:
                self.quota = quota

            if (
                attempt < self._max_retries
                and method in _RETRYABLE_METHODS
                and _is_retryable_status(response.status_code)
            ):
                wait = _retry_after_seconds(response.headers.get("retry-after"))
                if wait is not None:
                    time.sleep(wait)
                attempt += 1
                continue

            if response.status_code >= 400:
                raise _to_api_error(response, quota)

            if response.status_code == 204:
                return None
            return response.json()

    def _put_bytes(self, upload_url: str, method: str, headers: Mapping[str, str], payload: bytes) -> None:
        """Send attachment bytes straight to object storage. Not a /v1 call — no auth, no retry."""
        response = self._client.request(method, upload_url, headers=dict(headers), content=payload)
        if response.status_code >= 400:
            raise _upload_failed(response.status_code)


class AsyncFivexer:
    """Asynchronous typed client for the Fivexer Platform ``/v1`` API.

    Mirrors :class:`Fivexer` exactly but awaits results. Inject an
    ``httpx.AsyncClient`` (optionally with a ``MockTransport``) for testing.
    """

    def __init__(
        self,
        base_url: str,
        api_key: str,
        *,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.AsyncClient | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        if not api_key:
            raise ValueError("api_key is required")
        self._base = base_url.rstrip("/")
        self._api_key = api_key
        self._max_retries = max_retries
        self._owns_client = http_client is None
        self._client = http_client or httpx.AsyncClient(timeout=timeout)
        self.quota: QuotaInfo | None = None
        self.tasks = _AsyncTasks(self)
        self.workers = _AsyncWorkers(self)
        self.teams = _AsyncTeams(self)
        self.join_links = _AsyncJoinLinks(self)
        self.identities = _AsyncIdentities(self)
        self.skills = _AsyncSkills(self)
        self.decisions = _AsyncDecisions(self)
        self.workflows = _AsyncWorkflows(self)
        self.runs = _AsyncRuns(self)
        self.learning = _AsyncLearning(self)
        self.notifications = _AsyncNotifications(self)
        self.history = _AsyncHistory(self)
        self.team = _AsyncTeam(self)
        self.breaks = _AsyncBreaks(self)

    @property
    def base_url(self) -> str:
        return self._base

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    async def __aenter__(self) -> AsyncFivexer:
        return self

    async def __aexit__(self, *exc: Any) -> None:
        await self.aclose()

    async def stats(self) -> WorkspaceStats:
        return WorkspaceStats.from_json(await self._send(specs.stats()))

    async def sla_stats(self, tag: str | None = None) -> SlaStats:
        return SlaStats.from_json(await self._send(specs.stats_sla(tag)))

    async def queue_audit(self, query: QueueAuditQuery | None = None) -> QueueAuditReport:
        return QueueAuditReport.from_json(await self._send(specs.stats_queue_audit(query)))

    async def portal(self) -> WorkerPortalLink:
        return WorkerPortalLink.from_json(await self._send(specs.portal_link()))

    async def _send(self, spec: RequestSpec, *, idempotency_key: str | None = None) -> Any:
        return await self._request(
            spec.method, spec.path, body=spec.body, params=spec.query, idempotency_key=idempotency_key
        )

    async def _request(
        self,
        method: str,
        path: str,
        *,
        body: Any = None,
        params: Mapping[str, Any] | None = None,
        idempotency_key: str | None = None,
    ) -> Any:
        url = f"{self._base}/v1{path}"
        attempt = 0
        key = idempotency_key
        if key is None and method == "POST":
            key = str(uuid.uuid4())
        query = _params_to_str(params)

        while True:
            headers = _build_headers(self._api_key, body is not None, key)
            response = await self._client.request(
                method,
                url,
                headers=headers,
                json=body if body is not None else None,
                params=query,
            )

            quota = QuotaInfo.from_headers(dict(response.headers))
            if quota is not None:
                self.quota = quota

            if (
                attempt < self._max_retries
                and method in _RETRYABLE_METHODS
                and _is_retryable_status(response.status_code)
            ):
                wait = _retry_after_seconds(response.headers.get("retry-after"))
                if wait is not None:
                    await asyncio.sleep(wait)
                attempt += 1
                continue

            if response.status_code >= 400:
                raise _to_api_error(response, quota)

            if response.status_code == 204:
                return None
            return response.json()

    async def _put_bytes(
        self, upload_url: str, method: str, headers: Mapping[str, str], payload: bytes
    ) -> None:
        response = await self._client.request(method, upload_url, headers=dict(headers), content=payload)
        if response.status_code >= 400:
            raise _upload_failed(response.status_code)


# --------------------------------------------------------------------------- #
# Resource groups (sync)
# --------------------------------------------------------------------------- #


class _Tasks:
    def __init__(self, client: Fivexer) -> None:
        self._c = client
        self.context = _TaskContext(client)
        self.comments = _TaskComments(client)
        self.attachments = _TaskAttachments(client)

    def create_many(self, tasks: builtins.list[CreateTask]) -> BulkTaskReport:
        """Create many tasks in one call. Partial success is normal: read ``failed`` and the
        per-entry ``results``, which keep the caller's ordering via ``index``."""
        return BulkTaskReport.from_json(self._c._send(specs.tasks_create_many(tasks)))

    def check(self, task: CreateTask) -> TaskCheckReport:
        """Dry-run a task: who could take it, which tags nobody covers, what its policies get
        wrong. Creates and reserves nothing — safe to call on every keystroke of a form."""
        return TaskCheckReport.from_json(self._c._send(specs.tasks_check(task)))

    def ack(self, task_id: str, worker_id: str) -> TaskAction:
        """Acknowledge an offer without starting work — stops the response clock only."""
        return TaskAction.from_json(self._c._send(specs.tasks_ack(task_id, worker_id)))

    def escalate(self, task_id: str, worker_id: str | None = None) -> TaskEscalation:
        """Advance the escalation ladder now. ``parked`` comes back true when the ladder was
        already exhausted, which takes the task out of matching."""
        return TaskEscalation.from_json(self._c._send(specs.tasks_escalate(task_id, worker_id)))

    def parked(self) -> TaskList:
        """Tasks a policy gave up on — exhausted escalation, an SLA breach handled with park, or
        a rejection budget run dry. Out of matching, but recoverable with :meth:`unpark`."""
        return TaskList.from_json(self._c._send(specs.tasks_parked()))

    def scheduled(self) -> TaskList:
        """Tasks held by a ``schedule.not_before``, soonest activation first — the only view of
        work that is booked but has not started."""
        return TaskList.from_json(self._c._send(specs.tasks_scheduled()))

    def unpark(self, task_id: str, options: UnparkTask | None = None) -> TaskAction:
        """Return a parked task to the queue. Reset the clocks that parked it, or the next sweep
        may park it straight back."""
        return TaskAction.from_json(self._c._send(specs.tasks_unpark(task_id, options)))

    def create(self, task: CreateTask) -> Task:
        """Enqueue a task. Returns the created task with ``status='queued'``."""
        return _created_task(self._c._send(specs.tasks_create(task)))

    def get(self, task_id: str) -> Task:
        return Task.from_json(self._c._send(specs.tasks_get(task_id)))

    def list(self, query: ListTasksQuery | None = None) -> TaskPage:
        return TaskPage.from_json(self._c._send(specs.tasks_list(query)))

    def cancel(self, task_id: str) -> None:
        self._c._send(specs.tasks_cancel(task_id))

    def accept(self, task_id: str, worker_id: str) -> TaskAction:
        return TaskAction.from_json(self._c._send(specs.tasks_accept(task_id, worker_id)))

    def reject(self, task_id: str, worker_id: str) -> TaskAction:
        return TaskAction.from_json(self._c._send(specs.tasks_reject(task_id, worker_id)))

    def complete(
        self, task_id: str, worker_id: str, result: Mapping[str, Any] | None = None
    ) -> TaskAction:
        return TaskAction.from_json(self._c._send(specs.tasks_complete(task_id, worker_id, result)))

    def assign(self, task_id: str, worker_id: str, force: bool | None = None) -> AssignTaskResult:
        """Operator override. ``force`` bypasses paused/backlog/veto/prior-rejection checks."""
        return AssignTaskResult.from_json(self._c._send(specs.tasks_assign(task_id, worker_id, force)))

    def set_priority(self, task_id: str, priority: float) -> TaskPriority:
        return TaskPriority.from_json(self._c._send(specs.tasks_set_priority(task_id, priority)))

    def suggest_workers(self, request: SuggestWorkers) -> SuggestWorkersResult:
        """Dry run: who *would* match these tags, without creating a task."""
        return SuggestWorkersResult.from_json(self._c._send(specs.tasks_suggest_workers(request)))


class _TaskContext:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def get(self, task_id: str) -> TaskContext:
        return TaskContext.from_json(self._c._send(specs.context_get(task_id)))

    def set(self, task_id: str, context: SetTaskContext) -> TaskContext:
        return TaskContext.from_json(self._c._send(specs.context_set(task_id, context)))

    def clear(self, task_id: str) -> None:
        self._c._send(specs.context_clear(task_id))


class _TaskComments:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def add(self, task_id: str, comment: AddComment) -> Comment:
        return Comment.from_json(self._c._send(specs.comments_add(task_id, comment))["comment"])

    def list(self, task_id: str, cursor: str | None = None, limit: int | None = None) -> CommentPage:
        return CommentPage.from_json(self._c._send(specs.comments_list(task_id, cursor, limit)))

    def remove(self, task_id: str, comment_id: str) -> None:
        self._c._send(specs.comments_remove(task_id, comment_id))


class _TaskAttachments:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def create(self, task_id: str, attachment: CreateAttachment) -> CreatedAttachment:
        """Reserve an attachment record and get a presigned URL to PUT the bytes to."""
        return CreatedAttachment.from_json(self._c._send(specs.attachments_create(task_id, attachment)))

    def confirm(self, task_id: str, attachment_id: str) -> Attachment:
        return Attachment.from_json(
            self._c._send(specs.attachments_confirm(task_id, attachment_id))["attachment"]
        )

    def list(self, task_id: str) -> builtins.list[Attachment]:
        return _each(self._c._send(specs.attachments_list(task_id)), "attachments", Attachment.from_json)

    def download(self, task_id: str, attachment_id: str) -> AttachmentDownload:
        return AttachmentDownload.from_json(
            self._c._send(specs.attachments_download(task_id, attachment_id))
        )

    def remove(self, task_id: str, attachment_id: str) -> None:
        self._c._send(specs.attachments_remove(task_id, attachment_id))

    def upload(
        self,
        task_id: str,
        payload: bytes,
        filename: str,
        content_type: str,
        worker_id: str | None = None,
    ) -> Attachment:
        """Create → PUT the bytes to object storage → confirm, in one call.

        The size is derived from ``payload``, and ``upload.headers`` are sent verbatim because
        they are part of the presigned signature. A non-2xx from storage raises
        :class:`FivexerApiError` with code ``upload_failed``.
        """
        created = self.create(
            task_id,
            CreateAttachment(
                filename=filename,
                content_type=content_type,
                size_bytes=_size_of(payload),
                worker_id=worker_id,
            ),
        )
        self._c._put_bytes(
            created.upload.url, created.upload.method, created.upload.headers, bytes(payload)
        )
        return self.confirm(task_id, created.attachment.id)


class _Workers:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def upsert(self, worker: UpsertWorker | None = None) -> str:
        """Create or replace a worker. Returns the worker id (generated when not supplied)."""
        result: dict[str, Any] = self._c._send(specs.workers_upsert(worker))
        return str(result["id"])

    def list(self) -> WorkerList:
        return WorkerList.from_json(self._c._send(specs.workers_list()))

    def get(self, worker_id: str) -> WorkerDetail:
        return WorkerDetail.from_json(self._c._send(specs.workers_get(worker_id)))

    def patch(self, worker_id: str, patch: PatchWorker) -> str:
        """Partial update — absent fields keep their stored value."""
        result: dict[str, Any] = self._c._send(specs.workers_patch(worker_id, patch))
        return str(result["id"])

    def set_availability(
        self, worker_id: str, available: bool, release_backlog: bool = False
    ) -> WorkerAvailability:
        """Pause or resume a worker.

        A plain pause preserves the worker's unaccepted backlog ("back in ten minutes");
        ``release_backlog=True`` additionally requeues it so others inherit it now ("gone for
        the day"). Accepted work in progress is never touched. Only valid when pausing.
        """
        return WorkerAvailability.from_json(
            self._c._send(specs.workers_set_availability(worker_id, available, release_backlog))
        )

    def queue(self, worker_id: str) -> WorkerQueue:
        return WorkerQueue.from_json(self._c._send(specs.workers_queue(worker_id)))

    def remove(self, worker_id: str) -> None:
        self._c._send(specs.workers_remove(worker_id))


class _Teams:
    """Teams are a routing primitive, not just a label: each carries a `tag` that matching uses,
    so adding a worker to a team changes what work reaches them."""

    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def create(self, team: UpsertTeam) -> Team:
        return Team.from_json(self._c._send(specs.teams_create(team)))

    def list(self) -> builtins.list[Team]:
        return _each(self._c._send(specs.teams_list()), "teams", Team.from_json)

    def get(self, team_id: str) -> Team:
        return Team.from_json(self._c._send(specs.teams_get(team_id)))

    def patch(self, team_id: str, patch: PatchTeam) -> Team:
        return Team.from_json(self._c._send(specs.teams_patch(team_id, patch)))

    def remove(self, team_id: str) -> None:
        self._c._send(specs.teams_remove(team_id))

    def members(self, team_id: str) -> TeamMembers:
        return TeamMembers.from_json(self._c._send(specs.teams_members(team_id)))

    def set_members(self, team_id: str, worker_ids: builtins.list[str]) -> TeamRoster:
        """Replaces the roster wholesale — workers absent from `worker_ids` are removed."""
        return TeamRoster.from_json(self._c._send(specs.teams_set_members(team_id, worker_ids)))


class _JoinLinks:
    """QR join links: worker self-registration with preset tags/skills/team."""

    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def create(self, link: CreateJoinLink) -> CreateJoinLinkResult:
        """``join_url`` is returned only here — a lost link is re-created, never recovered."""
        return CreateJoinLinkResult.from_json(self._c._send(specs.join_links_create(link)))

    def list(self) -> builtins.list[JoinLink]:
        return _each(self._c._send(specs.join_links_list()), "links", JoinLink.from_json)

    def revoke(self, link_id: str) -> None:
        self._c._send(specs.join_links_revoke(link_id))


class _Identities:
    """Worker portal credentials. A worker (a routing target) and an identity (a way to sign in)
    are separate: `invite` creates both when the worker does not exist yet."""

    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self) -> builtins.list[PublicWorkerIdentity]:
        return _each(self._c._send(specs.identities_list()), "identities", PublicWorkerIdentity.from_json)

    def invite(self, invite: InviteWorkerIdentity) -> WorkerInviteResult:
        """Email a set-your-PIN link. Check ``email_status``: ``mailer_unconfigured`` means the
        deployment cannot send mail and ``invite_url`` is the only way to deliver it."""
        return WorkerInviteResult.from_json(self._c._send(specs.identities_invite(invite)))

    def resend_invite(self, worker_id: str) -> WorkerInviteResult:
        return WorkerInviteResult.from_json(self._c._send(specs.identities_resend_invite(worker_id)))

    def create(self, worker_id: str, identity: CreateWorkerIdentity) -> PublicWorkerIdentity:
        """Set a PIN directly, for a worker who will never receive email (a kiosk, an agent)."""
        return WorkerIdentityResult.from_json(
            self._c._send(specs.identities_create(worker_id, identity))
        ).identity

    def update(self, worker_id: str, patch: UpdateWorkerIdentity) -> PublicWorkerIdentity:
        return WorkerIdentityResult.from_json(
            self._c._send(specs.identities_update(worker_id, patch))
        ).identity

    def remove(self, worker_id: str) -> None:
        self._c._send(specs.identities_remove(worker_id))


class _Skills:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def create(self, skill: UpsertSkill) -> Skill:
        return Skill.from_json(self._c._send(specs.skills_create(skill)))

    def list(self, q: str | None = None, limit: int | None = None) -> builtins.list[Skill]:
        return _each(self._c._send(specs.skills_list(q, limit)), "skills", Skill.from_json)

    def get(self, skill_id: str) -> Skill:
        return Skill.from_json(self._c._send(specs.skills_get(skill_id)))

    def patch(self, skill_id: str, patch: PatchSkill) -> Skill:
        return Skill.from_json(self._c._send(specs.skills_patch(skill_id, patch)))

    def remove(self, skill_id: str) -> None:
        self._c._send(specs.skills_remove(skill_id))

    def suggest(
        self, selected: builtins.list[str] | None = None, limit: int | None = None
    ) -> builtins.list[Skill]:
        """Skills commonly held alongside the ones already selected."""
        return _each(self._c._send(specs.skills_suggest(selected, limit)), "skills", Skill.from_json)


class _Decisions:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self, query: ListDecisionsQuery | None = None) -> builtins.list[Decision]:
        return _each(self._c._send(specs.decisions_list(query)), "decisions", Decision.from_json)


class _Workflows:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self) -> builtins.list[WorkflowDefinitionSummary]:
        return _each(self._c._send(specs.workflows_list()), "workflows", WorkflowDefinitionSummary.from_json)

    def get(self, workflow_id: str) -> WorkflowDefinition:
        return WorkflowDefinition.from_json(self._c._send(specs.workflows_get(workflow_id)))

    def save(self, workflow_id: str, definition: WorkflowDefinitionInput) -> WorkflowDefinition:
        """Create or replace a definition. A rejected graph comes back as ``invalid_workflow``."""
        return WorkflowDefinition.from_json(self._c._send(specs.workflows_save(workflow_id, definition)))

    def remove(self, workflow_id: str) -> None:
        self._c._send(specs.workflows_remove(workflow_id))

    def run(self, workflow_id: str, start: StartRun | None = None) -> WorkflowRun:
        return WorkflowRun.from_json(self._c._send(specs.workflows_run(workflow_id, start)))

    def list_runs(self, workflow_id: str, query: ListRunsQuery | None = None) -> WorkflowRunPage:
        return WorkflowRunPage.from_json(self._c._send(specs.workflows_list_runs(workflow_id, query)))


class _Runs:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self, query: ListRunsQuery | None = None) -> WorkflowRunPage:
        return WorkflowRunPage.from_json(self._c._send(specs.runs_list(query)))

    def get(self, run_id: str) -> WorkflowRun:
        return WorkflowRun.from_json(self._c._send(specs.runs_get(run_id)))

    def steps(self, run_id: str) -> WorkflowRunSteps:
        return WorkflowRunSteps.from_json(self._c._send(specs.runs_steps(run_id)))

    def cancel(self, run_id: str) -> WorkflowRun:
        return WorkflowRun.from_json(self._c._send(specs.runs_cancel(run_id)))

    def complete_step(
        self, run_id: str, step_id: str, data: Mapping[str, Any] | None = None
    ) -> WorkflowRun:
        """Complete an external (callback) step, advancing the run."""
        return WorkflowRun.from_json(self._c._send(specs.runs_complete_step(run_id, step_id, data)))

    def fail_step(self, run_id: str, step_id: str, error: str | None = None) -> WorkflowRun:
        return WorkflowRun.from_json(self._c._send(specs.runs_fail_step(run_id, step_id, error)))


class _Learning:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def status(self) -> LearningStatus:
        return LearningStatus.from_json(self._c._send(specs.learning_status()))

    def worker_stats(self, worker_id: str) -> WorkerLearningStats:
        return WorkerLearningStats.from_json(self._c._send(specs.learning_worker_stats(worker_id)))

    def preview_weights(self, worker_id: str | None = None) -> LearnedWeightsPreview:
        return LearnedWeightsPreview.from_json(self._c._send(specs.learning_preview_weights(worker_id)))

    def apply_weights(
        self, worker_ids: builtins.list[str] | None = None
    ) -> dict[str, dict[str, float]]:
        result: dict[str, Any] = self._c._send(specs.learning_apply_weights(worker_ids))
        return dict(result.get("applied") or {})

    def revert_weights(self, worker_ids: builtins.list[str] | None = None) -> RevertedWeights:
        """Undo the last :meth:`apply_weights`, restoring each worker's saved snapshot. Workers
        who were never synced have nothing to restore and are absent from the result."""
        return RevertedWeights.from_json(self._c._send(specs.learning_revert_weights(worker_ids)))

    def feedback(self, task_id: str, signals: Mapping[str, float]) -> bool:
        result: dict[str, Any] = self._c._send(specs.learning_feedback(task_id, signals))
        return bool(result.get("ok", False))

    def reward(self, task_id: str, reward: float) -> bool:
        result: dict[str, Any] = self._c._send(specs.learning_reward(task_id, reward))
        return bool(result.get("ok", False))

    def feedback_bulk(
        self, items: builtins.list[LearningFeedbackItem]
    ) -> builtins.list[LearningFeedbackResult]:
        """Partial success: each result carries its own ``ok`` and optional ``error``."""
        return _each(
            self._c._send(specs.learning_feedback_bulk(items)), "results", LearningFeedbackResult.from_json
        )

    def reset(self) -> bool:
        result: dict[str, Any] = self._c._send(specs.learning_reset())
        return bool(result.get("ok", False))


class _Notifications:
    def __init__(self, client: Fivexer) -> None:
        self.sequences = _NotificationSequences(client)
        self.channels = _NotificationChannels(client)


class _NotificationSequences:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self) -> builtins.list[NotificationSequence]:
        return _each(self._c._send(specs.sequences_list()), "sequences", NotificationSequence.from_json)

    def create(self, sequence: CreateNotificationSequence) -> NotificationSequence:
        return NotificationSequence.from_json(self._c._send(specs.sequences_create(sequence)))

    def get(self, sequence_id: str) -> NotificationSequence:
        return NotificationSequence.from_json(self._c._send(specs.sequences_get(sequence_id)))

    def update(self, sequence_id: str, update: UpdateNotificationSequence) -> NotificationSequence:
        return NotificationSequence.from_json(self._c._send(specs.sequences_update(sequence_id, update)))

    def remove(self, sequence_id: str) -> None:
        self._c._send(specs.sequences_remove(sequence_id))


class _NotificationChannels:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def list(self) -> builtins.list[NotificationChannel]:
        return _each(self._c._send(specs.channels_list()), "channels", NotificationChannel.from_json)

    def create(self, channel: CreateNotificationChannel) -> NotificationChannel:
        return NotificationChannel.from_json(self._c._send(specs.channels_create(channel)))

    def get(self, channel_id: str) -> NotificationChannel:
        return NotificationChannel.from_json(self._c._send(specs.channels_get(channel_id)))

    def update(self, channel_id: str, update: UpdateNotificationChannel) -> NotificationChannel:
        return NotificationChannel.from_json(self._c._send(specs.channels_update(channel_id, update)))

    def remove(self, channel_id: str) -> None:
        self._c._send(specs.channels_remove(channel_id))


class _History:
    """Historical stats from the archive. Requires the control plane (501 otherwise)."""

    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def timeseries(self, query: StatsWindowQuery | None = None) -> StatsTimeseriesResult:
        return StatsTimeseriesResult.from_json(self._c._send(specs.stats_timeseries(query)))

    def workers(self, query: StatsWindowQuery | None = None) -> WorkerStatsResult:
        return WorkerStatsResult.from_json(self._c._send(specs.stats_workers(query)))


class _Team:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def presence(self) -> TeamPresence:
        """Who is working vs on break vs paused. Requires the control plane."""
        return TeamPresence.from_json(self._c._send(specs.team_presence()))


class _Breaks:
    def __init__(self, client: Fivexer) -> None:
        self._c = client

    def metrics(self, from_: str | None = None, to: str | None = None) -> WorkspaceBreakMetrics:
        """Per-worker break rollup for a window (defaults to today)."""
        return WorkspaceBreakMetrics.from_json(self._c._send(specs.breaks_metrics(from_, to)))


# --------------------------------------------------------------------------- #
# Resource groups (async) — same surface, awaited
# --------------------------------------------------------------------------- #


class _AsyncTasks:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client
        self.context = _AsyncTaskContext(client)
        self.comments = _AsyncTaskComments(client)
        self.attachments = _AsyncTaskAttachments(client)

    async def create_many(self, tasks: builtins.list[CreateTask]) -> BulkTaskReport:
        return BulkTaskReport.from_json(await self._c._send(specs.tasks_create_many(tasks)))

    async def check(self, task: CreateTask) -> TaskCheckReport:
        return TaskCheckReport.from_json(await self._c._send(specs.tasks_check(task)))

    async def ack(self, task_id: str, worker_id: str) -> TaskAction:
        return TaskAction.from_json(await self._c._send(specs.tasks_ack(task_id, worker_id)))

    async def escalate(self, task_id: str, worker_id: str | None = None) -> TaskEscalation:
        return TaskEscalation.from_json(await self._c._send(specs.tasks_escalate(task_id, worker_id)))

    async def parked(self) -> TaskList:
        return TaskList.from_json(await self._c._send(specs.tasks_parked()))

    async def scheduled(self) -> TaskList:
        return TaskList.from_json(await self._c._send(specs.tasks_scheduled()))

    async def unpark(self, task_id: str, options: UnparkTask | None = None) -> TaskAction:
        return TaskAction.from_json(await self._c._send(specs.tasks_unpark(task_id, options)))

    async def create(self, task: CreateTask) -> Task:
        return _created_task(await self._c._send(specs.tasks_create(task)))

    async def get(self, task_id: str) -> Task:
        return Task.from_json(await self._c._send(specs.tasks_get(task_id)))

    async def list(self, query: ListTasksQuery | None = None) -> TaskPage:
        return TaskPage.from_json(await self._c._send(specs.tasks_list(query)))

    async def cancel(self, task_id: str) -> None:
        await self._c._send(specs.tasks_cancel(task_id))

    async def accept(self, task_id: str, worker_id: str) -> TaskAction:
        return TaskAction.from_json(await self._c._send(specs.tasks_accept(task_id, worker_id)))

    async def reject(self, task_id: str, worker_id: str) -> TaskAction:
        return TaskAction.from_json(await self._c._send(specs.tasks_reject(task_id, worker_id)))

    async def complete(
        self, task_id: str, worker_id: str, result: Mapping[str, Any] | None = None
    ) -> TaskAction:
        return TaskAction.from_json(await self._c._send(specs.tasks_complete(task_id, worker_id, result)))

    async def assign(self, task_id: str, worker_id: str, force: bool | None = None) -> AssignTaskResult:
        return AssignTaskResult.from_json(
            await self._c._send(specs.tasks_assign(task_id, worker_id, force))
        )

    async def set_priority(self, task_id: str, priority: float) -> TaskPriority:
        return TaskPriority.from_json(await self._c._send(specs.tasks_set_priority(task_id, priority)))

    async def suggest_workers(self, request: SuggestWorkers) -> SuggestWorkersResult:
        return SuggestWorkersResult.from_json(await self._c._send(specs.tasks_suggest_workers(request)))


class _AsyncTaskContext:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def get(self, task_id: str) -> TaskContext:
        return TaskContext.from_json(await self._c._send(specs.context_get(task_id)))

    async def set(self, task_id: str, context: SetTaskContext) -> TaskContext:
        return TaskContext.from_json(await self._c._send(specs.context_set(task_id, context)))

    async def clear(self, task_id: str) -> None:
        await self._c._send(specs.context_clear(task_id))


class _AsyncTaskComments:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def add(self, task_id: str, comment: AddComment) -> Comment:
        result = await self._c._send(specs.comments_add(task_id, comment))
        return Comment.from_json(result["comment"])

    async def list(
        self, task_id: str, cursor: str | None = None, limit: int | None = None
    ) -> CommentPage:
        return CommentPage.from_json(await self._c._send(specs.comments_list(task_id, cursor, limit)))

    async def remove(self, task_id: str, comment_id: str) -> None:
        await self._c._send(specs.comments_remove(task_id, comment_id))


class _AsyncTaskAttachments:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def create(self, task_id: str, attachment: CreateAttachment) -> CreatedAttachment:
        return CreatedAttachment.from_json(
            await self._c._send(specs.attachments_create(task_id, attachment))
        )

    async def confirm(self, task_id: str, attachment_id: str) -> Attachment:
        result = await self._c._send(specs.attachments_confirm(task_id, attachment_id))
        return Attachment.from_json(result["attachment"])

    async def list(self, task_id: str) -> builtins.list[Attachment]:
        result = await self._c._send(specs.attachments_list(task_id))
        return _each(result, "attachments", Attachment.from_json)

    async def download(self, task_id: str, attachment_id: str) -> AttachmentDownload:
        return AttachmentDownload.from_json(
            await self._c._send(specs.attachments_download(task_id, attachment_id))
        )

    async def remove(self, task_id: str, attachment_id: str) -> None:
        await self._c._send(specs.attachments_remove(task_id, attachment_id))

    async def upload(
        self,
        task_id: str,
        payload: bytes,
        filename: str,
        content_type: str,
        worker_id: str | None = None,
    ) -> Attachment:
        created = await self.create(
            task_id,
            CreateAttachment(
                filename=filename,
                content_type=content_type,
                size_bytes=_size_of(payload),
                worker_id=worker_id,
            ),
        )
        await self._c._put_bytes(
            created.upload.url, created.upload.method, created.upload.headers, bytes(payload)
        )
        return await self.confirm(task_id, created.attachment.id)


class _AsyncWorkers:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def upsert(self, worker: UpsertWorker | None = None) -> str:
        result: dict[str, Any] = await self._c._send(specs.workers_upsert(worker))
        return str(result["id"])

    async def list(self) -> WorkerList:
        return WorkerList.from_json(await self._c._send(specs.workers_list()))

    async def get(self, worker_id: str) -> WorkerDetail:
        return WorkerDetail.from_json(await self._c._send(specs.workers_get(worker_id)))

    async def patch(self, worker_id: str, patch: PatchWorker) -> str:
        result: dict[str, Any] = await self._c._send(specs.workers_patch(worker_id, patch))
        return str(result["id"])

    async def set_availability(
        self, worker_id: str, available: bool, release_backlog: bool = False
    ) -> WorkerAvailability:
        return WorkerAvailability.from_json(
            await self._c._send(specs.workers_set_availability(worker_id, available, release_backlog))
        )

    async def queue(self, worker_id: str) -> WorkerQueue:
        return WorkerQueue.from_json(await self._c._send(specs.workers_queue(worker_id)))

    async def remove(self, worker_id: str) -> None:
        await self._c._send(specs.workers_remove(worker_id))


class _AsyncTeams:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def create(self, team: UpsertTeam) -> Team:
        return Team.from_json(await self._c._send(specs.teams_create(team)))

    async def list(self) -> builtins.list[Team]:
        return _each(await self._c._send(specs.teams_list()), "teams", Team.from_json)

    async def get(self, team_id: str) -> Team:
        return Team.from_json(await self._c._send(specs.teams_get(team_id)))

    async def patch(self, team_id: str, patch: PatchTeam) -> Team:
        return Team.from_json(await self._c._send(specs.teams_patch(team_id, patch)))

    async def remove(self, team_id: str) -> None:
        await self._c._send(specs.teams_remove(team_id))

    async def members(self, team_id: str) -> TeamMembers:
        return TeamMembers.from_json(await self._c._send(specs.teams_members(team_id)))

    async def set_members(self, team_id: str, worker_ids: builtins.list[str]) -> TeamRoster:
        return TeamRoster.from_json(await self._c._send(specs.teams_set_members(team_id, worker_ids)))


class _AsyncJoinLinks:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def create(self, link: CreateJoinLink) -> CreateJoinLinkResult:
        return CreateJoinLinkResult.from_json(await self._c._send(specs.join_links_create(link)))

    async def list(self) -> builtins.list[JoinLink]:
        return _each(await self._c._send(specs.join_links_list()), "links", JoinLink.from_json)

    async def revoke(self, link_id: str) -> None:
        await self._c._send(specs.join_links_revoke(link_id))


class _AsyncIdentities:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self) -> builtins.list[PublicWorkerIdentity]:
        return _each(
            await self._c._send(specs.identities_list()), "identities", PublicWorkerIdentity.from_json
        )

    async def invite(self, invite: InviteWorkerIdentity) -> WorkerInviteResult:
        return WorkerInviteResult.from_json(await self._c._send(specs.identities_invite(invite)))

    async def resend_invite(self, worker_id: str) -> WorkerInviteResult:
        return WorkerInviteResult.from_json(
            await self._c._send(specs.identities_resend_invite(worker_id))
        )

    async def create(self, worker_id: str, identity: CreateWorkerIdentity) -> PublicWorkerIdentity:
        return WorkerIdentityResult.from_json(
            await self._c._send(specs.identities_create(worker_id, identity))
        ).identity

    async def update(self, worker_id: str, patch: UpdateWorkerIdentity) -> PublicWorkerIdentity:
        return WorkerIdentityResult.from_json(
            await self._c._send(specs.identities_update(worker_id, patch))
        ).identity

    async def remove(self, worker_id: str) -> None:
        await self._c._send(specs.identities_remove(worker_id))


class _AsyncSkills:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def create(self, skill: UpsertSkill) -> Skill:
        return Skill.from_json(await self._c._send(specs.skills_create(skill)))

    async def list(self, q: str | None = None, limit: int | None = None) -> builtins.list[Skill]:
        return _each(await self._c._send(specs.skills_list(q, limit)), "skills", Skill.from_json)

    async def get(self, skill_id: str) -> Skill:
        return Skill.from_json(await self._c._send(specs.skills_get(skill_id)))

    async def patch(self, skill_id: str, patch: PatchSkill) -> Skill:
        return Skill.from_json(await self._c._send(specs.skills_patch(skill_id, patch)))

    async def remove(self, skill_id: str) -> None:
        await self._c._send(specs.skills_remove(skill_id))

    async def suggest(
        self, selected: builtins.list[str] | None = None, limit: int | None = None
    ) -> builtins.list[Skill]:
        return _each(await self._c._send(specs.skills_suggest(selected, limit)), "skills", Skill.from_json)


class _AsyncDecisions:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self, query: ListDecisionsQuery | None = None) -> builtins.list[Decision]:
        return _each(await self._c._send(specs.decisions_list(query)), "decisions", Decision.from_json)


class _AsyncWorkflows:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self) -> builtins.list[WorkflowDefinitionSummary]:
        result = await self._c._send(specs.workflows_list())
        return _each(result, "workflows", WorkflowDefinitionSummary.from_json)

    async def get(self, workflow_id: str) -> WorkflowDefinition:
        return WorkflowDefinition.from_json(await self._c._send(specs.workflows_get(workflow_id)))

    async def save(self, workflow_id: str, definition: WorkflowDefinitionInput) -> WorkflowDefinition:
        return WorkflowDefinition.from_json(
            await self._c._send(specs.workflows_save(workflow_id, definition))
        )

    async def remove(self, workflow_id: str) -> None:
        await self._c._send(specs.workflows_remove(workflow_id))

    async def run(self, workflow_id: str, start: StartRun | None = None) -> WorkflowRun:
        return WorkflowRun.from_json(await self._c._send(specs.workflows_run(workflow_id, start)))

    async def list_runs(self, workflow_id: str, query: ListRunsQuery | None = None) -> WorkflowRunPage:
        return WorkflowRunPage.from_json(
            await self._c._send(specs.workflows_list_runs(workflow_id, query))
        )


class _AsyncRuns:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self, query: ListRunsQuery | None = None) -> WorkflowRunPage:
        return WorkflowRunPage.from_json(await self._c._send(specs.runs_list(query)))

    async def get(self, run_id: str) -> WorkflowRun:
        return WorkflowRun.from_json(await self._c._send(specs.runs_get(run_id)))

    async def steps(self, run_id: str) -> WorkflowRunSteps:
        return WorkflowRunSteps.from_json(await self._c._send(specs.runs_steps(run_id)))

    async def cancel(self, run_id: str) -> WorkflowRun:
        return WorkflowRun.from_json(await self._c._send(specs.runs_cancel(run_id)))

    async def complete_step(
        self, run_id: str, step_id: str, data: Mapping[str, Any] | None = None
    ) -> WorkflowRun:
        return WorkflowRun.from_json(await self._c._send(specs.runs_complete_step(run_id, step_id, data)))

    async def fail_step(self, run_id: str, step_id: str, error: str | None = None) -> WorkflowRun:
        return WorkflowRun.from_json(await self._c._send(specs.runs_fail_step(run_id, step_id, error)))


class _AsyncLearning:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def status(self) -> LearningStatus:
        return LearningStatus.from_json(await self._c._send(specs.learning_status()))

    async def worker_stats(self, worker_id: str) -> WorkerLearningStats:
        return WorkerLearningStats.from_json(await self._c._send(specs.learning_worker_stats(worker_id)))

    async def preview_weights(self, worker_id: str | None = None) -> LearnedWeightsPreview:
        return LearnedWeightsPreview.from_json(
            await self._c._send(specs.learning_preview_weights(worker_id))
        )

    async def apply_weights(
        self, worker_ids: builtins.list[str] | None = None
    ) -> dict[str, dict[str, float]]:
        result: dict[str, Any] = await self._c._send(specs.learning_apply_weights(worker_ids))
        return dict(result.get("applied") or {})

    async def revert_weights(self, worker_ids: builtins.list[str] | None = None) -> RevertedWeights:
        return RevertedWeights.from_json(await self._c._send(specs.learning_revert_weights(worker_ids)))

    async def feedback(self, task_id: str, signals: Mapping[str, float]) -> bool:
        result: dict[str, Any] = await self._c._send(specs.learning_feedback(task_id, signals))
        return bool(result.get("ok", False))

    async def reward(self, task_id: str, reward: float) -> bool:
        result: dict[str, Any] = await self._c._send(specs.learning_reward(task_id, reward))
        return bool(result.get("ok", False))

    async def feedback_bulk(
        self, items: builtins.list[LearningFeedbackItem]
    ) -> builtins.list[LearningFeedbackResult]:
        result = await self._c._send(specs.learning_feedback_bulk(items))
        return _each(result, "results", LearningFeedbackResult.from_json)

    async def reset(self) -> bool:
        result: dict[str, Any] = await self._c._send(specs.learning_reset())
        return bool(result.get("ok", False))


class _AsyncNotifications:
    def __init__(self, client: AsyncFivexer) -> None:
        self.sequences = _AsyncNotificationSequences(client)
        self.channels = _AsyncNotificationChannels(client)


class _AsyncNotificationSequences:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self) -> builtins.list[NotificationSequence]:
        result = await self._c._send(specs.sequences_list())
        return _each(result, "sequences", NotificationSequence.from_json)

    async def create(self, sequence: CreateNotificationSequence) -> NotificationSequence:
        return NotificationSequence.from_json(await self._c._send(specs.sequences_create(sequence)))

    async def get(self, sequence_id: str) -> NotificationSequence:
        return NotificationSequence.from_json(await self._c._send(specs.sequences_get(sequence_id)))

    async def update(
        self, sequence_id: str, update: UpdateNotificationSequence
    ) -> NotificationSequence:
        return NotificationSequence.from_json(
            await self._c._send(specs.sequences_update(sequence_id, update))
        )

    async def remove(self, sequence_id: str) -> None:
        await self._c._send(specs.sequences_remove(sequence_id))


class _AsyncNotificationChannels:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def list(self) -> builtins.list[NotificationChannel]:
        result = await self._c._send(specs.channels_list())
        return _each(result, "channels", NotificationChannel.from_json)

    async def create(self, channel: CreateNotificationChannel) -> NotificationChannel:
        return NotificationChannel.from_json(await self._c._send(specs.channels_create(channel)))

    async def get(self, channel_id: str) -> NotificationChannel:
        return NotificationChannel.from_json(await self._c._send(specs.channels_get(channel_id)))

    async def update(self, channel_id: str, update: UpdateNotificationChannel) -> NotificationChannel:
        return NotificationChannel.from_json(
            await self._c._send(specs.channels_update(channel_id, update))
        )

    async def remove(self, channel_id: str) -> None:
        await self._c._send(specs.channels_remove(channel_id))


class _AsyncHistory:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def timeseries(self, query: StatsWindowQuery | None = None) -> StatsTimeseriesResult:
        return StatsTimeseriesResult.from_json(await self._c._send(specs.stats_timeseries(query)))

    async def workers(self, query: StatsWindowQuery | None = None) -> WorkerStatsResult:
        return WorkerStatsResult.from_json(await self._c._send(specs.stats_workers(query)))


class _AsyncTeam:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def presence(self) -> TeamPresence:
        return TeamPresence.from_json(await self._c._send(specs.team_presence()))


class _AsyncBreaks:
    def __init__(self, client: AsyncFivexer) -> None:
        self._c = client

    async def metrics(self, from_: str | None = None, to: str | None = None) -> WorkspaceBreakMetrics:
        return WorkspaceBreakMetrics.from_json(await self._c._send(specs.breaks_metrics(from_, to)))


def _created_task(result: Mapping[str, Any]) -> Task:
    """``POST /tasks`` answers with just ``{id, status}``; fill the rest in from the request."""
    return Task.from_json(
        {
            "id": result["id"],
            "tags": result.get("tags", []),
            "priority": result.get("priority"),
            "status": result.get("status", "queued"),
            "workerId": result.get("workerId"),
            "createdAt": result.get("createdAt"),
            "meta": result.get("meta"),
        }
    )
