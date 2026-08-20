"""Worker-facing client for the hosted portal — the ``wt_`` worker-token plane.

Deliberately separate from :class:`~fivexer.client.Fivexer` (workspace ``sk_`` key): a worker
token is scoped to exactly one worker and can never reach task-creation or worker-management
routes, so the type system should say so too.

A worker logs in with workspace id + worker id + PIN, then reads their queue and
accepts/rejects/completes their own tasks. :meth:`FivexerWorker.login` adopts both the returned
token and the worker id, so subsequent calls need no extra wiring.

Unlike the workspace plane, the portal plane emits no ``X-Quota-*`` headers and needs no
idempotency key, so this client's transport is deliberately simpler.
"""

from __future__ import annotations

import asyncio
import time
from collections.abc import Mapping
from typing import Any

import httpx

from . import _specs as specs
from ._specs import RequestSpec
from .client import (
    DEFAULT_TIMEOUT,
    _build_headers,
    _is_retryable_status,
    _parse_error_body,
    _retry_after_seconds,
)
from .errors import FivexerApiError
from .models import (
    AcceptWorkerInvite,
    AcceptWorkerInviteResult,
    ChangePin,
    Comment,
    CommentPage,
    JoinWorkspace,
    JoinWorkspaceResult,
    PushConfig,
    PushSubscription,
    PushSubscriptionInput,
    Skill,
    TaskAction,
    TeamPresence,
    WorkerAvailabilityState,
    WorkerBreakEnded,
    WorkerBreakStarted,
    WorkerBreakToday,
    WorkerDevice,
    WorkerDeviceInput,
    WorkerLocation,
    WorkerLocationResult,
    WorkerLogin,
    WorkerMe,
    WorkerMetricsToday,
    WorkerMetricsWindow,
    WorkerQueue,
    WorkerSessionToken,
    WorkerSkillLevel,
    WorkerSkillSet,
    WorkerTaskDetail,
    _each,
)

# Retried like the workspace plane, minus POST: portal actions are not idempotent and carry no
# Idempotency-Key, so replaying one could accept a task twice.
_RETRYABLE_METHODS = frozenset({"GET", "HEAD"})


def _worker_id_required() -> FivexerApiError:
    return FivexerApiError(
        400,
        "worker_id_required",
        "worker_id is required — call login() first or pass it explicitly",
        None,
        None,
    )


def _portal_headers(token: str | None, body_present: bool) -> dict[str, str]:
    """Same shape as the workspace plane, but the token is optional (login has none yet)."""
    if token is None:
        headers = {"accept": "application/json"}
        if body_present:
            headers["content-type"] = "application/json"
        return headers
    return _build_headers(token, body_present, None)


def _to_error(response: httpx.Response) -> FivexerApiError:
    body = _parse_error_body(response.text)
    err = body.get("error") if isinstance(body.get("error"), dict) else None
    code = (err or {}).get("code") or "unknown_error"
    message = (err or {}).get("message") or f"http {response.status_code}"
    return FivexerApiError(
        response.status_code, code, message, None, _retry_after_seconds(response.headers.get("retry-after"))
    )


class FivexerWorker:
    """Synchronous client for the worker portal plane.

    Example::

        worker = FivexerWorker(base_url="https://api.fivexer.com")
        worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))
        queue = worker.queue()
        worker.accept(queue.task_ids[0])
    """

    def __init__(
        self,
        base_url: str,
        *,
        token: str | None = None,
        worker_id: str | None = None,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.Client | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        self._base = base_url.rstrip("/")
        self._token = token
        self._worker_id = worker_id
        self._max_retries = max_retries
        self._owns_client = http_client is None
        self._client = http_client or httpx.Client(timeout=timeout)

    @property
    def base_url(self) -> str:
        return self._base

    @property
    def session_token(self) -> str | None:
        """The current worker session token, if logged in."""
        return self._token

    @property
    def worker_id(self) -> str | None:
        """The worker this client acts as."""
        return self._worker_id

    def set_token(self, token: str | None, worker_id: str | None = None) -> None:
        """Adopt a token (and optionally the worker id it belongs to) from a prior session."""
        self._token = token
        if worker_id is not None:
            self._worker_id = worker_id

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def __enter__(self) -> FivexerWorker:
        return self

    def __exit__(self, *exc: Any) -> None:
        self.close()

    # -- auth --
    def login(self, login: WorkerLogin) -> WorkerSessionToken:
        """Log in and adopt the returned token plus the worker id for subsequent calls."""
        session = WorkerSessionToken.from_json(self._send(specs.worker_login(login)))
        self._token = session.token
        self._worker_id = login.worker_id
        return session

    def logout(self) -> None:
        """Revoke the session and forget the token."""
        self._send(specs.worker_logout())
        self._token = None

    # -- tasks --
    def queue(self, worker_id: str | None = None) -> WorkerQueue:
        return WorkerQueue.from_json(self._send(specs.worker_queue(self._require(worker_id))))

    def task_detail(self, task_id: str) -> WorkerTaskDetail:
        """Rich detail of a task assigned to this worker (404 for another worker's task)."""
        return WorkerTaskDetail.from_json(self._send(specs.worker_task_detail(task_id)))

    def accept(self, task_id: str, worker_id: str | None = None) -> TaskAction:
        return TaskAction.from_json(
            self._send(specs.worker_task_action(task_id, "accept", self._require(worker_id)))
        )

    def reject(self, task_id: str, worker_id: str | None = None) -> TaskAction:
        """Reject a task — it requeues for other eligible workers."""
        return TaskAction.from_json(
            self._send(specs.worker_task_action(task_id, "reject", self._require(worker_id)))
        )

    def complete(
        self,
        task_id: str,
        result: Mapping[str, Any] | None = None,
        worker_id: str | None = None,
    ) -> TaskAction:
        return TaskAction.from_json(
            self._send(specs.worker_complete(task_id, self._require(worker_id), result))
        )

    # -- breaks, metrics, presence --
    def start_break(self, reason: str | None = None) -> WorkerBreakStarted:
        """Pause routing to this worker. The current backlog is kept; 409 if already on break."""
        return WorkerBreakStarted.from_json(self._send(specs.worker_start_break(reason)))

    def end_break(self) -> WorkerBreakEnded | None:
        """End the break and resume routing. Returns ``None`` when no break was open."""
        result = self._send(specs.worker_end_break())
        return None if result is None else WorkerBreakEnded.from_json(result)

    def breaks_today(self) -> WorkerBreakToday:
        return WorkerBreakToday.from_json(self._send(specs.worker_breaks_today()))

    def metrics_today(self) -> WorkerMetricsToday:
        return WorkerMetricsToday.from_json(self._send(specs.worker_metrics_today()))

    def team_presence(self) -> TeamPresence:
        """Read-only team presence — visible to every worker in the workspace."""
        return TeamPresence.from_json(self._send(specs.worker_team_presence()))

    # -- internals --
    def refresh(self) -> bool:
        """Rotate the session token in place. Returns False when the server says
        re-authenticate (any 4xx); anything else — network, 5xx, an older server with no
        ``/refresh`` route — is raised, leaving the current token usable for the caller's retry."""
        if not self._token:
            return False
        try:
            session = WorkerSessionToken.from_json(self._send(specs.worker_refresh()))
        except FivexerApiError as error:
            if 400 <= error.status_code < 500:
                return False
            raise
        self._token = session.token
        return True

    def join(self, join: JoinWorkspace) -> JoinWorkspaceResult:
        """Self-register through a QR join link, adopting the returned session. The worker id is
        generated server-side — show it to them, it is their PIN-login username. When the link
        required approval, ``pending_approval`` is true and no work is routed until an operator
        resumes them."""
        result = JoinWorkspaceResult.from_json(self._send(specs.worker_join(join)))
        self._token = result.token
        self._worker_id = result.worker_id
        return result

    def accept_invite(self, invite: AcceptWorkerInvite) -> AcceptWorkerInviteResult:
        """Consume an emailed invite token and set this worker's PIN, adopting the returned
        session. The token is single-use: replaying it is a 400 (``invalid_token``), which is also
        what an expired link returns."""
        result = AcceptWorkerInviteResult.from_json(self._send(specs.worker_accept_invite(invite)))
        self._token = result.token
        self._worker_id = result.worker_id
        return result

    # -- shift / availability --
    def me(self) -> WorkerMe:
        """Who am I, and am I on shift? Works on data-plane-only deployments, where the break and
        team endpoints 501 (``on_break`` is simply always false there)."""
        return WorkerMe.from_json(self._send(specs.worker_me()))

    def set_availability(self, available: bool) -> WorkerAvailabilityState:
        """Go on or off shift — the worker's own switch. Workers are created off shift, so this is
        what makes someone matchable in the first place. Off shift keeps the existing backlog;
        going available also ends an open break. Raises 403 ``approval_pending`` while an operator
        still has to admit a QR-join worker."""
        return WorkerAvailabilityState.from_json(self._send(specs.worker_set_availability(available)))

    def change_pin(self, change: ChangePin) -> None:
        """A wrong ``current_pin`` is a 400 (``invalid_current_pin``); the session stays valid
        either way."""
        self._send(specs.worker_change_pin(change))

    # -- own skills --
    def skill_catalog(self) -> list[Skill]:
        """The workspace's catalog — what this worker may claim. Read-only: inventing a skill is an
        operator's decision, so a worker picks from this list or picks nothing."""
        return _each(self._send(specs.worker_skill_catalog()), "skills", Skill.from_json)

    def set_skills(self, skills: list[WorkerSkillLevel]) -> WorkerSkillSet:
        """Declare what this worker can do, replacing their whole set. Does *not* change shift
        state: ``WorkerMe.skill_setup_pending`` asks the question, this answers it, and going on
        shift stays a separate claim."""
        return WorkerSkillSet.from_json(self._send(specs.worker_set_skills(skills)))

    # -- comments --
    def comments(self, task_id: str, cursor: str | None = None, limit: int | None = None) -> CommentPage:
        return CommentPage.from_json(self._send(specs.worker_comments_list(task_id, cursor, limit)))

    def add_comment(self, task_id: str, body: str) -> Comment:
        return Comment.from_json(self._send(specs.worker_comments_add(task_id, body))["comment"])

    # -- metrics / location --
    def metrics_window(self, window: str = "7d") -> WorkerMetricsWindow:
        return WorkerMetricsWindow.from_json(self._send(specs.worker_metrics_window(window)))

    def update_location(self, location: WorkerLocation) -> WorkerLocationResult:
        """Rate-limited server-side (one update per ~10s) to keep churn off the matching plane."""
        return WorkerLocationResult.from_json(self._send(specs.worker_update_location(location)))

    # -- push notifications --
    def register_device(self, device: WorkerDeviceInput) -> WorkerDevice:
        """Register an Expo push token for the native app. Browsers use :meth:`push_subscribe`."""
        return WorkerDevice.from_json(self._send(specs.worker_device_register(device)))

    def unregister_device(self, token: str) -> None:
        """Call on logout, or when notification permission is revoked."""
        self._send(specs.worker_device_unregister(token))

    def push_config(self) -> PushConfig:
        """Read this before prompting for notification permission: ``enabled=False`` means the
        deployment has no VAPID keypair and the prompt would be wasted."""
        return PushConfig.from_json(self._send(specs.worker_push_config()))

    def push_subscribe(self, subscription: PushSubscriptionInput) -> PushSubscription:
        return PushSubscription.from_json(self._send(specs.worker_push_subscribe(subscription)))

    def push_unsubscribe(self, endpoint: str) -> None:
        self._send(specs.worker_push_unsubscribe(endpoint))

    def _require(self, worker_id: str | None) -> str:

        resolved = worker_id or self._worker_id
        if not resolved:
            raise _worker_id_required()
        return resolved

    def _send(self, spec: RequestSpec) -> Any:
        url = f"{self._base}/v1{spec.path}"
        attempt = 0
        while True:
            response = self._client.request(
                spec.method,
                url,
                headers=_portal_headers(self._token, spec.body is not None),
                params=spec.query or None,
                json=spec.body if spec.body is not None else None,
            )
            if (
                attempt < self._max_retries
                and spec.method in _RETRYABLE_METHODS
                and _is_retryable_status(response.status_code)
            ):
                wait = _retry_after_seconds(response.headers.get("retry-after"))
                if wait is not None:
                    time.sleep(wait)
                attempt += 1
                continue

            if response.status_code >= 400:
                raise _to_error(response)
            if response.status_code == 204:
                return None
            return response.json()


class AsyncFivexerWorker:
    """Asynchronous mirror of :class:`FivexerWorker`."""

    def __init__(
        self,
        base_url: str,
        *,
        token: str | None = None,
        worker_id: str | None = None,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.AsyncClient | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        self._base = base_url.rstrip("/")
        self._token = token
        self._worker_id = worker_id
        self._max_retries = max_retries
        self._owns_client = http_client is None
        self._client = http_client or httpx.AsyncClient(timeout=timeout)

    @property
    def base_url(self) -> str:
        return self._base

    @property
    def session_token(self) -> str | None:
        return self._token

    @property
    def worker_id(self) -> str | None:
        return self._worker_id

    def set_token(self, token: str | None, worker_id: str | None = None) -> None:
        self._token = token
        if worker_id is not None:
            self._worker_id = worker_id

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    async def __aenter__(self) -> AsyncFivexerWorker:
        return self

    async def __aexit__(self, *exc: Any) -> None:
        await self.aclose()

    async def login(self, login: WorkerLogin) -> WorkerSessionToken:
        session = WorkerSessionToken.from_json(await self._send(specs.worker_login(login)))
        self._token = session.token
        self._worker_id = login.worker_id
        return session

    async def logout(self) -> None:
        await self._send(specs.worker_logout())
        self._token = None

    async def queue(self, worker_id: str | None = None) -> WorkerQueue:
        return WorkerQueue.from_json(await self._send(specs.worker_queue(self._require(worker_id))))

    async def task_detail(self, task_id: str) -> WorkerTaskDetail:
        return WorkerTaskDetail.from_json(await self._send(specs.worker_task_detail(task_id)))

    async def accept(self, task_id: str, worker_id: str | None = None) -> TaskAction:
        return TaskAction.from_json(
            await self._send(specs.worker_task_action(task_id, "accept", self._require(worker_id)))
        )

    async def reject(self, task_id: str, worker_id: str | None = None) -> TaskAction:
        return TaskAction.from_json(
            await self._send(specs.worker_task_action(task_id, "reject", self._require(worker_id)))
        )

    async def complete(
        self,
        task_id: str,
        result: Mapping[str, Any] | None = None,
        worker_id: str | None = None,
    ) -> TaskAction:
        return TaskAction.from_json(
            await self._send(specs.worker_complete(task_id, self._require(worker_id), result))
        )

    async def start_break(self, reason: str | None = None) -> WorkerBreakStarted:
        return WorkerBreakStarted.from_json(await self._send(specs.worker_start_break(reason)))

    async def end_break(self) -> WorkerBreakEnded | None:
        result = await self._send(specs.worker_end_break())
        return None if result is None else WorkerBreakEnded.from_json(result)

    async def breaks_today(self) -> WorkerBreakToday:
        return WorkerBreakToday.from_json(await self._send(specs.worker_breaks_today()))

    async def metrics_today(self) -> WorkerMetricsToday:
        return WorkerMetricsToday.from_json(await self._send(specs.worker_metrics_today()))

    async def team_presence(self) -> TeamPresence:
        return TeamPresence.from_json(await self._send(specs.worker_team_presence()))

    async def refresh(self) -> bool:
        if not self._token:
            return False
        try:
            session = WorkerSessionToken.from_json(await self._send(specs.worker_refresh()))
        except FivexerApiError as error:
            if 400 <= error.status_code < 500:
                return False
            raise
        self._token = session.token
        return True

    async def join(self, join: JoinWorkspace) -> JoinWorkspaceResult:
        result = JoinWorkspaceResult.from_json(await self._send(specs.worker_join(join)))
        self._token = result.token
        self._worker_id = result.worker_id
        return result

    async def accept_invite(self, invite: AcceptWorkerInvite) -> AcceptWorkerInviteResult:
        result = AcceptWorkerInviteResult.from_json(
            await self._send(specs.worker_accept_invite(invite))
        )
        self._token = result.token
        self._worker_id = result.worker_id
        return result

    async def me(self) -> WorkerMe:
        return WorkerMe.from_json(await self._send(specs.worker_me()))

    async def set_availability(self, available: bool) -> WorkerAvailabilityState:
        return WorkerAvailabilityState.from_json(
            await self._send(specs.worker_set_availability(available))
        )

    async def change_pin(self, change: ChangePin) -> None:
        await self._send(specs.worker_change_pin(change))

    async def skill_catalog(self) -> list[Skill]:
        return _each(await self._send(specs.worker_skill_catalog()), "skills", Skill.from_json)

    async def set_skills(self, skills: list[WorkerSkillLevel]) -> WorkerSkillSet:
        return WorkerSkillSet.from_json(await self._send(specs.worker_set_skills(skills)))

    async def comments(
        self, task_id: str, cursor: str | None = None, limit: int | None = None
    ) -> CommentPage:
        return CommentPage.from_json(
            await self._send(specs.worker_comments_list(task_id, cursor, limit))
        )

    async def add_comment(self, task_id: str, body: str) -> Comment:
        return Comment.from_json(
            (await self._send(specs.worker_comments_add(task_id, body)))["comment"]
        )

    async def metrics_window(self, window: str = "7d") -> WorkerMetricsWindow:
        return WorkerMetricsWindow.from_json(await self._send(specs.worker_metrics_window(window)))

    async def update_location(self, location: WorkerLocation) -> WorkerLocationResult:
        return WorkerLocationResult.from_json(
            await self._send(specs.worker_update_location(location))
        )

    async def register_device(self, device: WorkerDeviceInput) -> WorkerDevice:
        return WorkerDevice.from_json(await self._send(specs.worker_device_register(device)))

    async def unregister_device(self, token: str) -> None:
        await self._send(specs.worker_device_unregister(token))

    async def push_config(self) -> PushConfig:
        return PushConfig.from_json(await self._send(specs.worker_push_config()))

    async def push_subscribe(self, subscription: PushSubscriptionInput) -> PushSubscription:
        return PushSubscription.from_json(
            await self._send(specs.worker_push_subscribe(subscription))
        )

    async def push_unsubscribe(self, endpoint: str) -> None:
        await self._send(specs.worker_push_unsubscribe(endpoint))

    def _require(self, worker_id: str | None) -> str:

        resolved = worker_id or self._worker_id
        if not resolved:
            raise _worker_id_required()
        return resolved

    async def _send(self, spec: RequestSpec) -> Any:
        url = f"{self._base}/v1{spec.path}"
        attempt = 0
        while True:
            response = await self._client.request(
                spec.method,
                url,
                headers=_portal_headers(self._token, spec.body is not None),
                params=spec.query or None,
                json=spec.body if spec.body is not None else None,
            )
            if (
                attempt < self._max_retries
                and spec.method in _RETRYABLE_METHODS
                and _is_retryable_status(response.status_code)
            ):
                wait = _retry_after_seconds(response.headers.get("retry-after"))
                if wait is not None:
                    await asyncio.sleep(wait)
                attempt += 1
                continue

            if response.status_code >= 400:
                raise _to_error(response)
            if response.status_code == 204:
                return None
            return response.json()
