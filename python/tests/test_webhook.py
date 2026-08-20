"""Webhook signature verification: replay window, malformed headers, constant-time compare.

Mirrors the platform's ``x-fivexer-signature`` signing (``t=<unix-ms>,v1=<hex64 HMAC-SHA256>``
over ``"<timestamp>.<raw-body>"``). The body passed must be the raw bytes received.
"""

from __future__ import annotations

import hashlib
import hmac
import json

import pytest

from fivexer import SIGNATURE_HEADER, SignatureVerificationError, Webhook

SECRET = "whsec_testsecret"
NOW = 1750000000000


def _sign(secret: str, timestamp: int, body: str) -> str:
    return f"t={timestamp},v1={hmac.new(secret.encode(), f'{timestamp}.{body}'.encode(), hashlib.sha256).hexdigest()}"


def _body() -> bytes:
    return json.dumps({"event": "task.matched", "taskId": "task_8fk2", "workerId": "agent_1"}).encode()


def test_a_validly_signed_webhook_is_verified_and_parsed():
    raw = _body()
    header = _sign(SECRET, NOW, raw.decode())

    event = Webhook.construct_event(payload=raw, header=header, secret=SECRET, now_ms=NOW)

    assert event.event == "task.matched"
    assert event.data["taskId"] == "task_8fk2"
    assert event.data["workerId"] == "agent_1"


def test_a_webhook_signed_with_the_wrong_secret_is_rejected():
    raw = _body()
    header = _sign("whsec_wrong", NOW, raw.decode())

    with pytest.raises(SignatureVerificationError):
        Webhook.construct_event(payload=raw, header=header, secret=SECRET, now_ms=NOW)


def test_a_webhook_outside_the_replay_window_is_rejected():
    raw = _body()
    # signed 10 minutes ago, tolerance is 5 minutes
    header = _sign(SECRET, NOW - (10 * 60 * 1000), raw.decode())

    with pytest.raises(SignatureVerificationError):
        Webhook.construct_event(payload=raw, header=header, secret=SECRET, now_ms=NOW)


def test_a_malformed_signature_header_is_rejected():
    raw = _body()
    for bad in ["", "garbage", "t=abc,v1=def", "t=123", "v1=abc", "t=123,v1=" + "x" * 64]:
        with pytest.raises(SignatureVerificationError):
            Webhook.construct_event(payload=raw, header=bad, secret=SECRET, now_ms=NOW)


def test_a_tampered_body_fails_verification():
    raw = _body()
    header = _sign(SECRET, NOW, raw.decode())
    tampered = raw.replace(b"task_8fk2", b"task_9999")

    with pytest.raises(SignatureVerificationError):
        Webhook.construct_event(payload=tampered, header=header, secret=SECRET, now_ms=NOW)


def test_a_custom_tolerance_window_can_be_widened():
    raw = _body()
    header = _sign(SECRET, NOW - (6 * 60 * 1000), raw.decode())  # 6 min old

    # default tolerance (5 min) rejects it
    with pytest.raises(SignatureVerificationError):
        Webhook.construct_event(payload=raw, header=header, secret=SECRET, now_ms=NOW)
    # widened tolerance (10 min) accepts it
    event = Webhook.construct_event(payload=raw, header=header, secret=SECRET, now_ms=NOW, tolerance_ms=10 * 60 * 1000)
    assert event.event == "task.matched"


def test_signature_header_constant_is_the_platform_value():
    assert SIGNATURE_HEADER == "x-fivexer-signature"


def test_verify_signature_returns_false_without_raising():
    assert Webhook.verify_signature(b"{}", "garbage", SECRET, now_ms=NOW) is False
