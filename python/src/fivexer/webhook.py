"""Webhook signature verification for ``task.matched`` and other events.

Mirrors the platform's signing exactly:
    header   : x-fivexer-signature
    format   : t=<unix-ms>,v1=<hex64 HMAC-SHA256>
    message  : "<timestamp>.<raw-body>"
    replay   : rejected if |now - timestamp| > tolerance (default ±5 min)
    compare  : constant-time hex digest equality

The body must be the **raw** request body (bytes or str) — exactly what was received,
before any JSON re-serialization, otherwise the HMAC will not match.
"""

from __future__ import annotations

import hashlib
import hmac as _hmac
import json
import time
from typing import Any, Union

from .errors import SignatureVerificationError

SIGNATURE_HEADER = "x-fivexer-signature"
DEFAULT_TOLERANCE_MS = 5 * 60 * 1000

BodyLike = Union[bytes, str]


class Webhook:
    """Pure webhook helpers. No network."""

    @staticmethod
    def construct_event(
        payload: BodyLike,
        header: str,
        secret: str,
        tolerance_ms: int = DEFAULT_TOLERANCE_MS,
        now_ms: int | None = None,
    ) -> WebhookEvent:
        """Verify the signature and return the parsed event.

        Raises :class:`SignatureVerificationError` if the header is malformed, the
        timestamp is outside the tolerance window, or the signature does not match.
        """
        if not Webhook.verify_signature(payload, header, secret, tolerance_ms, now_ms):
            raise SignatureVerificationError("webhook signature verification failed")
        return WebhookEvent.from_payload(payload)

    @staticmethod
    def verify_signature(
        payload: BodyLike,
        header: str,
        secret: str,
        tolerance_ms: int = DEFAULT_TOLERANCE_MS,
        now_ms: int | None = None,
    ) -> bool:
        parsed = _parse_signature_header(header)
        if parsed is None:
            return False
        timestamp, signature = parsed

        current = now_ms if now_ms is not None else int(time.time() * 1000)
        if abs(current - timestamp) > tolerance_ms:
            return False

        body = payload.decode("utf-8") if isinstance(payload, (bytes, bytearray)) else payload
        expected = _compute_signature(secret, timestamp, body)
        return _hmac.compare_digest(expected, signature)


def _compute_signature(secret: str, timestamp: int, body: str) -> str:
    message = f"{timestamp}.{body}"
    return _hmac.new(secret.encode("utf-8"), message.encode("utf-8"), hashlib.sha256).hexdigest()


def _parse_signature_header(header: str) -> tuple[int, str] | None:
    """``t=<digits>,v1=<64 hex>``. Returns (timestamp_ms, signature_hex) or None."""
    if header is None:
        return None
    header = header.strip()
    # tolerate a single leading/trailing space; the platform emits no spaces after commas
    parts = header.split(",")
    if len(parts) != 2:
        return None
    t_part, v_part = parts[0].strip(), parts[1].strip()
    if not t_part.startswith("t=") or not v_part.startswith("v1="):
        return None
    ts_str = t_part[2:]
    sig = v_part[3:]
    if not ts_str.isdigit():
        return None
    # exactly 64 lowercase hex chars (sha256)
    if len(sig) != 64 or any(c not in "0123456789abcdef" for c in sig.lower()):
        return None
    return int(ts_str), sig.lower()


class WebhookEvent:
    """A verified webhook payload. ``data`` is the parsed JSON object."""

    def __init__(self, event: str | None, data: Any) -> None:
        self.event = event
        self.data = data

    @classmethod
    def from_payload(cls, payload: BodyLike) -> WebhookEvent:
        body = payload.decode("utf-8") if isinstance(payload, (bytes, bytearray)) else payload
        parsed = json.loads(body)
        # The platform sends { "event": "task.matched", ...fields } for webhooks.
        if isinstance(parsed, dict) and "event" in parsed:
            return cls(event=parsed.get("event"), data=parsed)
        return cls(event=None, data=parsed)

    def __repr__(self) -> str:  # pragma: no cover - cosmetic
        return f"WebhookEvent(event={self.event!r})"
