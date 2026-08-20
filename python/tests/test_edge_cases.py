"""Edge cases that close the remaining coverage gaps: error repr, owned-client close,
async constructor validation/property, invalid Retry-After, async quota + retry-with-sleep,
async TypeError guards, async decisions, and webhook payload/header edge branches.
"""

from __future__ import annotations

import asyncio

import httpx
import pytest

from fivexer import (
    AsyncFivexer,
    CreateTask,
    Fivexer,
    FivexerApiError,
    ListDecisionsQuery,
    Webhook,
)
from tests.conftest import json_response

# ---- error repr & owned client close (sync) ----


def test_fivexer_api_error_repr_includes_status_and_code(server, client):
    server.set_response(json_response(404, {"error": {"code": "not_found", "message": "task not found"}}))
    with pytest.raises(FivexerApiError) as exc:
        client.tasks.get("missing")
    text = repr(exc.value)
    assert "404" in text and "not_found" in text


def test_owned_client_is_closed_via_context_manager():
    with Fivexer(base_url="https://api.fivexer.test", api_key="sk_x") as client:
        assert client.base_url == "https://api.fivexer.test"
    # closing an owned client must not raise
    client.close()


# ---- invalid Retry-After falls back to None (no sleep, retry continues) ----


def test_invalid_retry_after_value_yields_none_on_the_error(server, client):
    server.set_response(
        json_response(429, {"error": {"code": "rate_limited", "message": "slow down"}},
                      headers={"retry-after": "not-a-number"})
    )
    with pytest.raises(FivexerApiError) as exc:
        client.tasks.list()
    assert exc.value.retry_after is None


# ---- async constructor validation + base_url property ----


def test_async_constructor_rejects_missing_base_url_and_key():
    with pytest.raises(ValueError):
        AsyncFivexer(base_url="", api_key="k")
    with pytest.raises(ValueError):
        AsyncFivexer(base_url="https://x", api_key="")


def test_async_base_url_strips_trailing_slash():
    client = AsyncFivexer(base_url="https://api.fivexer.test/", api_key="k")
    assert client.base_url == "https://api.fivexer.test"


# ---- async quota parsing + retry-with-sleep (retry-after present) ----


def test_async_quota_headers_attach_to_client_and_error():
    async def main():
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                202,
                json={"id": "task_1", "status": "queued"},
                headers={"x-quota-task-rate-limit": "100", "x-quota-task-rate-remaining": "99"},
            )

        client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="k",
                              http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)))
        await client.tasks.create(CreateTask(tags=["x"]))
        q = client.quota
        await client.aclose()
        return q

    q = asyncio.run(main())
    assert q is not None and q.task_rate_limit == 100


def test_async_retry_sleeps_then_raises_when_still_rate_limited():
    async def main():
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(429, json={"error": {"code": "rate_limited", "message": "slow down"}},
                                  headers={"retry-after": "0"})

        client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="k", max_retries=1,
                              http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)))
        with pytest.raises(FivexerApiError) as exc:
            await client.tasks.create(CreateTask(tags=["x"]))
        await client.aclose()
        return exc.value.status_code

    assert asyncio.run(main()) == 429


# ---- async TypeError guards ----


def test_async_create_rejects_non_createtask():
    async def main():
        transport = httpx.MockTransport(lambda r: json_response(200, {}))
        client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="k",
                              http_client=httpx.AsyncClient(transport=transport))
        with pytest.raises(TypeError):
            await client.tasks.create({"tags": ["x"]})  # type: ignore[arg-type]
        await client.aclose()

    asyncio.run(main())


def test_async_upsert_rejects_non_upsertworker():
    async def main():
        transport = httpx.MockTransport(lambda r: json_response(200, {"id": "w"}))
        client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="k",
                              http_client=httpx.AsyncClient(transport=transport))
        with pytest.raises(TypeError):
            await client.workers.upsert({"id": "w"})  # type: ignore[arg-type]
        await client.aclose()

    asyncio.run(main())


# ---- async decisions list ----


def test_async_decisions_list_parses_candidates():
    async def main():
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(200, json={"decisions": [
                {"id": "d1", "taskId": "t1", "workerId": "a1", "matchedAt": 1, "mode": "best-match",
                 "candidates": [{"workerId": "a1", "score": 5}]}]})

        client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="k",
                              http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)))
        decisions = await client.decisions.list(ListDecisionsQuery(task_id="t1"))
        await client.aclose()
        return decisions

    decisions = asyncio.run(main())
    assert decisions[0].worker_id == "a1"
    assert decisions[0].candidates[0].detail == {"score": 5}


# ---- webhook header edge branches ----


def test_webhook_payload_without_event_key_yields_none_event():
    raw = b'{"taskId": "task_1", "workerId": "a1"}'
    # sign the exact raw body so verification passes
    import hashlib
    import hmac

    ts = 1750000000000
    sig = hmac.new(b"whsec_x", f"{ts}.".encode() + raw, hashlib.sha256).hexdigest()
    header = f"t={ts},v1={sig}"

    event = Webhook.construct_event(payload=raw, header=header, secret="whsec_x", now_ms=ts)
    assert event.event is None
    assert event.data["taskId"] == "task_1"


def test_webhook_malformed_header_variants_all_rejected():
    from fivexer import SignatureVerificationError

    raw = b'{"event": "task.matched"}'
    malformed = [
        "",
        "just-one-part",
        "x=1,v1=" + "a" * 64,
        "t=12.5,v1=" + "0" * 64,
        "t=12345,v1=nothex!",
    ]
    for bad in malformed:
        with pytest.raises(SignatureVerificationError):
            Webhook.construct_event(payload=raw, header=bad, secret="whsec_x", now_ms=1750000000000)
