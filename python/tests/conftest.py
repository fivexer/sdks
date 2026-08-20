"""Shared test fixtures: a Fivexer client wired to an httpx MockTransport.

Tests assert against the public SDK API (black box) and inspect the exact HTTP the SDK
emits (method, path, query, headers, body) — that wire shape *is* the /v1 contract, not an
implementation detail. No real network is touched.
"""

from __future__ import annotations

import json
from typing import Any, Callable

import httpx
import pytest

from fivexer import (
    AsyncFivexer,
    AsyncFivexerSupervisor,
    AsyncFivexerWorker,
    Fivexer,
    FivexerSupervisor,
    FivexerWorker,
)

BASE_URL = "https://api.fivexer.test"


class MockServer:
    """Records every request the SDK makes and serves canned responses."""

    def __init__(self) -> None:
        self.requests: list[httpx.Request] = []
        self._responder: Callable[[httpx.Request], httpx.Response] | None = None

    def set_response(self, response: httpx.Response) -> None:
        self._responder = lambda _req: response

    def set_responder(self, fn: Callable[[httpx.Request], httpx.Response]) -> None:
        self._responder = fn

    def handler(self, request: httpx.Request) -> httpx.Response:
        self.requests.append(request)
        if self._responder is None:
            return httpx.Response(500, json={"error": {"code": "no_responder", "message": "test misconfigured"}})
        return self._responder(request)

    @property
    def last(self) -> httpx.Request:
        return self.requests[-1]

    def reset(self) -> None:
        self.requests.clear()
        self._responder = None


@pytest.fixture
def server() -> MockServer:
    return MockServer()


@pytest.fixture
def client(server: MockServer) -> Fivexer:
    transport = httpx.MockTransport(server.handler)
    return Fivexer(
        base_url=BASE_URL,
        api_key="sk_test_abc123",
        max_retries=0,
        http_client=httpx.Client(transport=transport),
    )


@pytest.fixture
def async_client(server: MockServer) -> AsyncFivexer:
    transport = httpx.MockTransport(server.handler)
    return AsyncFivexer(
        base_url=BASE_URL,
        api_key="sk_test_abc123",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=transport),
    )


@pytest.fixture
def worker(server: MockServer) -> FivexerWorker:
    """A worker-portal client already holding a session token, as if login() had run."""
    transport = httpx.MockTransport(server.handler)
    return FivexerWorker(
        base_url=BASE_URL,
        token="wt_s3ss10n",
        worker_id="agent_1",
        max_retries=0,
        http_client=httpx.Client(transport=transport),
    )


@pytest.fixture
def anon_worker(server: MockServer) -> FivexerWorker:
    """A worker-portal client with no token yet — the pre-login state."""
    transport = httpx.MockTransport(server.handler)
    return FivexerWorker(
        base_url=BASE_URL,
        max_retries=0,
        http_client=httpx.Client(transport=transport),
    )


@pytest.fixture
def async_worker(server: MockServer) -> AsyncFivexerWorker:
    transport = httpx.MockTransport(server.handler)
    return AsyncFivexerWorker(
        base_url=BASE_URL,
        token="wt_s3ss10n",
        worker_id="agent_1",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=transport),
    )


@pytest.fixture
def supervisor(server: MockServer) -> FivexerSupervisor:
    """A supervisor client already holding a session, as if accept_invite() had run."""
    transport = httpx.MockTransport(server.handler)
    return FivexerSupervisor(
        BASE_URL,
        token="sv_s3ss10n",
        max_retries=0,
        http_client=httpx.Client(transport=transport),
    )


@pytest.fixture
def anon_supervisor(server: MockServer) -> FivexerSupervisor:
    """A supervisor client with no session yet — the pre-redemption state."""
    transport = httpx.MockTransport(server.handler)
    return FivexerSupervisor(BASE_URL, max_retries=0, http_client=httpx.Client(transport=transport))


@pytest.fixture
def async_supervisor(server: MockServer) -> AsyncFivexerSupervisor:
    transport = httpx.MockTransport(server.handler)
    return AsyncFivexerSupervisor(
        BASE_URL,
        token="sv_s3ss10n",
        max_retries=0,
        http_client=httpx.AsyncClient(transport=transport),
    )


def routed(routes: dict[tuple[str, str], httpx.Response]) -> Callable[[httpx.Request], httpx.Response]:
    """Build a responder that answers by ``(method, path)``, for multi-call round trips."""

    def handler(request: httpx.Request) -> httpx.Response:
        response = routes.get((request.method, request.url.path))
        if response is None:  # pragma: no cover - a miss means the test wired the wrong path
            return httpx.Response(500, json={"error": {"code": "unrouted", "message": request.url.path}})
        return response

    return handler


def json_response(
    status: int = 200,
    body: dict[str, Any] | None = None,
    headers: dict[str, str] | None = None,
) -> httpx.Response:
    return httpx.Response(status, json=body if body is not None else {}, headers=headers or {})


def empty_response(status: int = 204, headers: dict[str, str] | None = None) -> httpx.Response:
    return httpx.Response(status, headers=headers or {})


def read_body(request: httpx.Request) -> dict[str, Any]:
    raw = request.content.decode("utf-8") if request.content else "{}"
    return json.loads(raw) if raw else {}


def query_pairs(request: httpx.Request) -> dict[str, str]:
    return dict(request.url.params)
