"""Every operation in the contract catalogue is reachable from this SDK.

``contract/operations.yaml`` is the shared checklist all four SDKs are held to. This test walks
it and resolves each operation name to a real callable, so an endpoint added to the platform
cannot quietly go missing here — the failure names the exact operation.

Parsed with a small regex rather than PyYAML: the catalogue's operation names are a flat list of
``- name: group.method`` lines, and this keeps the test suite dependency-free.
"""

from __future__ import annotations

import re
from pathlib import Path

import pytest

from fivexer import (
    AsyncFivexer,
    AsyncFivexerSupervisor,
    AsyncFivexerWorker,
    Fivexer,
    FivexerSupervisor,
    FivexerWorker,
)

CATALOGUE = Path(__file__).resolve().parents[2] / "contract" / "operations.yaml"

# Wire names are camelCase; the Python surface is snake_case. Only the genuinely different
# names need listing — the rest fall out of a mechanical conversion.
RENAMED = {
    "tasks.suggestWorkers": "tasks.suggest_workers",
    "tasks.setPriority": "tasks.set_priority",
    "tasks.comments.remove": "tasks.comments.remove",
    "workers.setAvailability": "workers.set_availability",
    "workflows.listRuns": "workflows.list_runs",
    "runs.completeStep": "runs.complete_step",
    "runs.failStep": "runs.fail_step",
    "learning.workerStats": "learning.worker_stats",
    "learning.previewWeights": "learning.preview_weights",
    "learning.applyWeights": "learning.apply_weights",
    "learning.feedbackBulk": "learning.feedback_bulk",
    "history.timeseries": "history.timeseries",
    "team.presence": "team.presence",
    "breaks.metrics": "breaks.metrics",
}


def _snake(name: str) -> str:
    return re.sub(r"(?<!^)(?=[A-Z])", "_", name).lower()


def _python_path(operation: str) -> str:
    """Wire name -> Python attribute path.

    The conversion is mechanical: camelCase segments become snake_case, and the session planes'
    `worker.` / `supervisor.` prefixes are dropped, because each is a separate client rather
    than a resource group on the workspace one. RENAMED holds only the handful that break that rule, so a new operation
    needs an entry there only when it genuinely diverges.
    """
    if operation in RENAMED:
        return RENAMED[operation]
    for prefix in ("worker.", "supervisor."):
        if operation.startswith(prefix):
            operation = operation[len(prefix) :]
            break
    return ".".join(_snake(part) for part in operation.split("."))


def _operations() -> list[str]:
    text = CATALOGUE.read_text(encoding="utf-8")
    # Only the `groups:` section describes HTTP operations; `helpers:` below it lists the
    # non-HTTP surface (webhook verification, the quota snapshot), which has no client method.
    groups = text.split("\ngroups:", 1)[1].split("\nhelpers:", 1)[0]
    # Operation entries look like `- name: tasks.create`; the group headers use `- group:`.
    return re.findall(r"^\s+- name: ([a-zA-Z][\w.]*)$", groups, re.M)


def _resolve(root: object, dotted: str) -> object:
    current = root
    for part in dotted.split("."):
        current = getattr(current, part)
    return current


ALL_OPERATIONS = _operations()
# Three credential planes, three clients. An operation belongs to whichever plane's prefix it
# carries; everything unprefixed is the workspace (`sk_`) plane.
WORKER_OPS = [op for op in ALL_OPERATIONS if op.startswith("worker.")]
SUPERVISOR_OPS = [op for op in ALL_OPERATIONS if op.startswith("supervisor.")]
WORKSPACE_OPS = [
    op for op in ALL_OPERATIONS if not op.startswith(("worker.", "supervisor."))
]


def test_the_catalogue_was_actually_read():
    # A silent regex miss would make every parity assertion below vacuously pass.
    assert len(ALL_OPERATIONS) >= 80
    assert "tasks.create" in ALL_OPERATIONS
    assert "worker.login" in WORKER_OPS
    assert "supervisor.overview" in SUPERVISOR_OPS


@pytest.mark.parametrize("operation", WORKSPACE_OPS)
def test_every_workspace_operation_exists_on_the_sync_client(operation):
    path = _python_path(operation)
    client = Fivexer(base_url="https://api.fivexer.test", api_key="sk_test")

    assert callable(_resolve(client, path)), f"{operation} is missing from Fivexer"


@pytest.mark.parametrize("operation", WORKSPACE_OPS)
def test_every_workspace_operation_exists_on_the_async_client(operation):
    path = _python_path(operation)
    client = AsyncFivexer(base_url="https://api.fivexer.test", api_key="sk_test")

    assert callable(_resolve(client, path)), f"{operation} is missing from AsyncFivexer"


@pytest.mark.parametrize("operation", WORKER_OPS)
def test_every_worker_portal_operation_exists_on_both_worker_clients(operation):
    path = _python_path(operation)

    assert callable(_resolve(FivexerWorker(base_url="https://api.fivexer.test"), path))
    assert callable(_resolve(AsyncFivexerWorker(base_url="https://api.fivexer.test"), path))


@pytest.mark.parametrize("operation", SUPERVISOR_OPS)
def test_every_supervisor_operation_exists_on_both_supervisor_clients(operation):
    path = _python_path(operation)

    assert callable(_resolve(FivexerSupervisor("https://api.fivexer.test"), path))
    assert callable(_resolve(AsyncFivexerSupervisor("https://api.fivexer.test"), path))
