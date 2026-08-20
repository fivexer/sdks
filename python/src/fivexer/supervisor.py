"""Supervisor-plane clients — the ``sv_`` session token.

A third credential type alongside the workspace key (:class:`~fivexer.client.Fivexer`) and the
worker token (:class:`~fivexer.worker.FivexerWorker`). A supervisor watches and unblocks work
rather than doing it: they can unpark a task, reprioritise it, hand it to a crew member and
pause one; they can never create work, manage the roster, or reach the workspace plane. Scope
is either one team or the whole workspace, and every action is checked against it server-side —
a crew lead cannot push their crew's work onto another crew, or pull another crew's work in.

Unlike the worker plane there is no login and no refresh. A session begins by redeeming a
single-use link an owner generated in the console, and ends when it expires or is revoked.
There is nothing to rotate with, so an expired session means "get a new link" — these clients
deliberately have no recovery path, because inventing one would only hide that.
"""

from __future__ import annotations

import asyncio
import time
from typing import Any

import httpx

from . import _specs as specs
from ._specs import RequestSpec
from .client import (
    DEFAULT_TIMEOUT,
    _is_retryable_status,
    _retry_after_seconds,
)
from .models import (
    AcceptSupervisorInvite,
    PushConfig,
    PushSubscriptionInput,
    SupervisorAssignResult,
    SupervisorAvailabilityResult,
    SupervisorEntry,
    SupervisorMe,
    SupervisorOverview,
    SupervisorSession,
    TaskAction,
    TaskPriority,
    UnparkTask,
)

# The worker plane's error mapper, reused rather than re-copied: both planes answer with the
# same `{"error": {code, message}}` envelope and neither carries quota headers. A third copy is
# a third place for the envelope's shape to drift.
from .worker import _to_error  # noqa: E402  (after .models, to keep the import graph acyclic)

# Reads only, mirroring the worker plane: a supervisor action carries no idempotency key, so a
# replayed assign could move a task twice.
_RETRYABLE_METHODS = frozenset({"GET", "HEAD"})


def _supervisor_headers(token: str | None, body_present: bool) -> dict[str, str]:
    headers = {"Accept": "application/json"}
    # `entry` and `accept` run before a token exists; an empty bearer would be a malformed
    # request rather than an anonymous one.
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if body_present:
        headers["Content-Type"] = "application/json"
    return headers


class FivexerSupervisor:
    """Synchronous supervisor client."""

    def __init__(
        self,
        base_url: str,
        token: str | None = None,
        *,
        expires_at: int | None = None,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.Client | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        self._base = base_url.rstrip("/")
        self._token = token
        self._expires_at = expires_at
        self._max_retries = max_retries
        self._owns_client = http_client is None
        self._client = http_client or httpx.Client(timeout=timeout)

    @property
    def base_url(self) -> str:
        return self._base

    @property
    def session_token(self) -> str | None:
        return self._token

    @property
    def session_expires_at(self) -> int | None:
        """Epoch-milliseconds, as the wire sends it — not the ISO string the worker plane uses."""
        return self._expires_at

    def set_token(self, token: str | None, expires_at: int | None = None) -> None:
        """Adopt a supervisor token from a prior session."""
        self._token = token
        self._expires_at = expires_at

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def __enter__(self) -> FivexerSupervisor:
        return self

    def __exit__(self, *exc: Any) -> None:
        self.close()

    # -- session --
    def entry(self) -> SupervisorEntry:
        """Where a supervisor can sign in from. Unauthenticated."""
        return SupervisorEntry.from_json(self._send(specs.supervisor_entry()))

    def accept_invite(self, invite: AcceptSupervisorInvite) -> SupervisorSession:
        """Redeem a supervisor link, adopting the returned session.

        The token is single-use. Expired, already-used, revoked and never-existed all answer
        with the same 400 ``invalid_token`` — the server refuses to tell a grinder which half
        of a guess was right, so the SDK cannot distinguish them either.
        """
        session = SupervisorSession.from_json(self._send(specs.supervisor_accept(invite)))
        self.set_token(session.token, session.expires_at)
        return session

    def logout(self) -> None:
        """End the session and forget the token.

        Also drops this supervisor's push subscriptions server-side: a handed-over phone must
        stop buzzing with another crew's work, so signing out and unsubscribing are one
        trust boundary rather than two steps a caller can get half-right.
        """
        self._send(specs.supervisor_logout())
        self.set_token(None, None)

    # -- board --
    def me(self) -> SupervisorMe:
        """The signed-in supervisor and their scope. ``team_key=None`` means the whole workspace."""
        return SupervisorMe.from_json(self._send(specs.supervisor_me()))

    def overview(self) -> SupervisorOverview:
        """The whole board in one request: counts, crew (busiest first) and parked work."""
        return SupervisorOverview.from_json(self._send(specs.supervisor_overview()))

    # -- actions --
    def unpark(self, task_id: str, options: UnparkTask | None = None) -> TaskAction:
        """Return a parked task to the queue. Reset the clocks that parked it, or the next
        sweep may park it straight back."""
        return TaskAction.from_json(self._send(specs.supervisor_unpark(task_id, options)))

    def set_priority(self, task_id: str, priority: float) -> TaskPriority:
        return TaskPriority.from_json(self._send(specs.supervisor_set_priority(task_id, priority)))

    def assign(
        self, task_id: str, worker_id: str, force: bool | None = None
    ) -> SupervisorAssignResult:
        """Hand a task to a specific crew member.

        Both ends are scope-checked: a task from another crew, or a worker outside this one,
        is a 403 rather than a silent move. A refusal from the matcher (paused, backlog full,
        prior rejection) surfaces as 400 ``assign_blocked``; ``force`` bypasses those checks
        but never worker existence.
        """
        return SupervisorAssignResult.from_json(
            self._send(specs.supervisor_assign(task_id, worker_id, force))
        )

    def set_availability(
        self, worker_id: str, available: bool, release_backlog: bool | None = None
    ) -> SupervisorAvailabilityResult:
        """Pause or resume a crew member.

        Pausing never releases work implicitly — that is the engine's rule.
        ``release_backlog`` is the explicit redistribution move, and only *pending* work
        moves; anything already accepted stays with whoever accepted it.
        """
        return SupervisorAvailabilityResult.from_json(
            self._send(specs.supervisor_set_availability(worker_id, available, release_backlog))
        )

    # -- push --
    def push_config(self) -> PushConfig:
        """Read before prompting: ``enabled=False`` means no VAPID keypair on this deployment."""
        return PushConfig.from_json(self._send(specs.supervisor_push_config()))

    def push_subscribe(self, subscription: PushSubscriptionInput) -> bool:
        """Register a browser subscription against this supervisor's own table.

        Returns the server's bare acknowledgement rather than a subscription record — the
        supervisor table is keyed by endpoint and has nothing else to hand back, which is where
        this differs from the worker plane's equivalent.
        """
        data = self._send(specs.supervisor_push_subscribe(subscription))
        return bool((data or {}).get("ok", False))

    def push_unsubscribe(self, endpoint: str) -> None:
        self._send(specs.supervisor_push_unsubscribe(endpoint))

    # -- transport --
    def _send(self, spec: RequestSpec) -> Any:
        url = f"{self._base}/v1{spec.path}"
        attempt = 0
        while True:
            response = self._client.request(
                spec.method,
                url,
                headers=_supervisor_headers(self._token, spec.body is not None),
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


class AsyncFivexerSupervisor:
    """Asynchronous mirror of :class:`FivexerSupervisor`."""

    def __init__(
        self,
        base_url: str,
        token: str | None = None,
        *,
        expires_at: int | None = None,
        timeout: float = DEFAULT_TIMEOUT,
        max_retries: int = 1,
        http_client: httpx.AsyncClient | None = None,
    ) -> None:
        if not base_url:
            raise ValueError("base_url is required")
        self._base = base_url.rstrip("/")
        self._token = token
        self._expires_at = expires_at
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
    def session_expires_at(self) -> int | None:
        return self._expires_at

    def set_token(self, token: str | None, expires_at: int | None = None) -> None:
        self._token = token
        self._expires_at = expires_at

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    async def __aenter__(self) -> AsyncFivexerSupervisor:
        return self

    async def __aexit__(self, *exc: Any) -> None:
        await self.aclose()

    async def entry(self) -> SupervisorEntry:
        return SupervisorEntry.from_json(await self._send(specs.supervisor_entry()))

    async def accept_invite(self, invite: AcceptSupervisorInvite) -> SupervisorSession:
        session = SupervisorSession.from_json(await self._send(specs.supervisor_accept(invite)))
        self.set_token(session.token, session.expires_at)
        return session

    async def logout(self) -> None:
        await self._send(specs.supervisor_logout())
        self.set_token(None, None)

    async def me(self) -> SupervisorMe:
        return SupervisorMe.from_json(await self._send(specs.supervisor_me()))

    async def overview(self) -> SupervisorOverview:
        return SupervisorOverview.from_json(await self._send(specs.supervisor_overview()))

    async def unpark(self, task_id: str, options: UnparkTask | None = None) -> TaskAction:
        return TaskAction.from_json(await self._send(specs.supervisor_unpark(task_id, options)))

    async def set_priority(self, task_id: str, priority: float) -> TaskPriority:
        return TaskPriority.from_json(
            await self._send(specs.supervisor_set_priority(task_id, priority))
        )

    async def assign(
        self, task_id: str, worker_id: str, force: bool | None = None
    ) -> SupervisorAssignResult:
        return SupervisorAssignResult.from_json(
            await self._send(specs.supervisor_assign(task_id, worker_id, force))
        )

    async def set_availability(
        self, worker_id: str, available: bool, release_backlog: bool | None = None
    ) -> SupervisorAvailabilityResult:
        return SupervisorAvailabilityResult.from_json(
            await self._send(
                specs.supervisor_set_availability(worker_id, available, release_backlog)
            )
        )

    async def push_config(self) -> PushConfig:
        return PushConfig.from_json(await self._send(specs.supervisor_push_config()))

    async def push_subscribe(self, subscription: PushSubscriptionInput) -> bool:
        data = await self._send(specs.supervisor_push_subscribe(subscription))
        return bool((data or {}).get("ok", False))

    async def push_unsubscribe(self, endpoint: str) -> None:
        await self._send(specs.supervisor_push_unsubscribe(endpoint))

    async def _send(self, spec: RequestSpec) -> Any:
        url = f"{self._base}/v1{spec.path}"
        attempt = 0
        while True:
            response = await self._client.request(
                spec.method,
                url,
                headers=_supervisor_headers(self._token, spec.body is not None),
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


__all__ = ["AsyncFivexerSupervisor", "FivexerSupervisor"]
