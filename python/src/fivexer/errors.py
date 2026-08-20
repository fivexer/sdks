"""Structured errors raised by the Fivexer client."""

from __future__ import annotations

if False:  # pragma: no cover - typing only
    from .models import QuotaInfo


class FivexerError(Exception):
    """Base class for all Fivexer client errors."""


class FivexerApiError(FivexerError):
    """Raised when the /v1 API returns a non-2xx response.

    Mirrors the platform error envelope ``{ "error": { "code", "message" } }``.
    The ``code`` matches the API's error codes (e.g. ``rate_limited``,
    ``plan_limit_exceeded``, ``not_found``, ``task_exists``, ``validation_failed``).
    On ``402``/``429`` the quota snapshot at the moment of rejection is attached.
    """

    def __init__(
        self,
        status_code: int,
        code: str,
        message: str,
        quota: QuotaInfo | None = None,
        retry_after: float | None = None,
    ) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.code = code
        self.quota = quota
        self.retry_after = retry_after

    def __repr__(self) -> str:
        return f"FivexerApiError(status_code={self.status_code!r}, code={self.code!r}, message={self!s})"


class SignatureVerificationError(FivexerError):
    """Raised by :class:`fivexer.Webhook` when a webhook cannot be verified."""
