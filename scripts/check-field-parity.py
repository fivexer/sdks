#!/usr/bin/env python3
"""Field-level parity: every wire field the reference TypeScript SDK knows, in all three others.

`scripts/sync-contract.py` grades *operations* — does every /v1 endpoint have a method behind it
in each SDK. That gate cannot see inside a request or response body, which is where the drift
this script was written for actually happened: `tasks.create` existed in all four SDKs while
three of them silently dropped `escalation`, `sla`, `schedule`, `recurrence`, `requiredSkills`,
`teamId` and `preferTeamId` from the body. Every parity test passed the whole time.

So: walk `platform/packages/sdk/src/types.ts`, keep the interfaces reachable from the three
planes the hand-written SDKs cover (workspace, worker, supervisor — *not* the console/account
plane), and check each of their fields against the Python, Java and PHP sources.

The two classification tables below are the point of the design. A TS type that has no
same-named model in the other SDKs is either an alias, a shape those SDKs model as plain method
arguments, or console-only — and each has to be *said*, with a reason. An unclassified one is a
gap and fails the run, so the next `CreateTaskInput` cannot go unnoticed the way the last one
did.

What this does not catch: it asks whether an SDK's model *knows a field name at all*, not
whether the field is wired end to end. A field declared and then dropped on the way to the wire
still passes here — that is what the per-SDK request-building tests are for. The drift this
closes is the coarser one it was written for: a field that was never added at all.

Usage:
    scripts/check-field-parity.py            # report, exit 1 on any gap
    scripts/check-field-parity.py --verbose  # also list what was classified and why

Platform location resolves in this order: --platform, $FIVEXER_PLATFORM, ../platform.
Without a platform checkout there is nothing to compare against and the script skips, exit 0 —
the same "say so rather than claim a pass it did not earn" rule sync-contract.py follows.
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]

# ---------------------------------------------------------------------------
# Classification: why a TS type has no same-named model in the hand-written SDKs
# ---------------------------------------------------------------------------

#: TS interface -> the model class the other SDKs actually call it. Same shape, different name.
ALIAS = {
    "CreateTaskInput": "CreateTask",
    "UpsertWorkerInput": "UpsertWorker",
    "PatchWorkerInput": "PatchWorker",
    "SuggestWorkersInput": "SuggestWorkers",
    "AddCommentInput": "AddComment",
    "CreateAttachmentInput": "CreateAttachment",
    "WorkerCreateAttachmentInput": "WorkerCreateAttachment",
    "UpsertSkillInput": "UpsertSkill",
    "PatchSkillInput": "PatchSkill",
    "UpsertTeamInput": "UpsertTeam",
    "PatchTeamInput": "PatchTeam",
    "SetTaskContextInput": "SetTaskContext",
    "StartRunInput": "StartRun",
    "CreateJoinLinkInput": "CreateJoinLink",
    "ChangePinInput": "ChangePin",
    "AcceptWorkerInviteInput": "AcceptWorkerInvite",
    "AcceptSupervisorInviteInput": "AcceptSupervisorInvite",
    "CreateNotificationChannelInput": "CreateNotificationChannel",
    "UpdateNotificationChannelInput": "UpdateNotificationChannel",
    "CreateNotificationSequenceInput": "CreateNotificationSequence",
    "UpdateNotificationSequenceInput": "UpdateNotificationSequence",
    "CreateWorkerIdentityInput": "CreateWorkerIdentity",
    "UpdateWorkerIdentityInput": "UpdateWorkerIdentity",
    "InviteWorkerIdentityInput": "InviteWorkerIdentity",
    "JoinWorkspaceInput": "JoinWorkspace",
    "WorkerLoginInput": "WorkerLogin",
    "WorkerLocationInput": "WorkerLocation",
    "UnparkTaskInput": "UnparkTask",
    "PushSubscriptionInput": "PushSubscriptionInput",
    "WorkerDeviceInput": "WorkerDeviceInput",
    "WorkerTaskResult": "TaskAction",
}

#: Shapes the hand-written SDKs deliberately express as method parameters rather than a model.
#: A one-or-two-field body does not earn a class in a language where the call reads fine
#: without one — but the decision has to be recorded, or "no model" stops meaning anything.
AS_ARGUMENTS = {
    "TaskFeedbackInput": "feedback(taskId, signals) — one field, passed directly",
    "TaskRewardInput": "reward(taskId, reward) — one field, passed directly",
    "StepOutcomeInput": "completeStep(runId, stepId, data) / failStep(..., error)",
    "LearnedWeightsOptions": "previewWeights/applyWeights take the two flags as parameters",
    "ListTasksQuery": "list(status, cursor, limit)",
    "ListDecisionsQuery": "list(taskId, workerId, limit)",
    "ListRunsQuery": "listRuns(workflowId, status, cursor, limit)",
    "QueueAuditQuery": "queueAudit(limit, minWaitingMs, includeHealthy)",
    "StatsWindowQuery": "timeseries(from, to, bucket)",
    "WorkerStatsQuery": "workerTimeseries(workerId, from, to, bucket, teamId)",
    "BreakMetricsQuery": "breaks.metrics(from, to, teamId, workerId)",
}

#: Reachable from a plane the SDKs cover, but never actually on that plane's wire. The server
#: does not send these fields to a workspace key, and the console/account plane that does own
#: them is a TypeScript-only surface — so mirroring them in three SDKs would model a response
#: nobody receives.
CONSOLE_PLANE_ONLY = {
    "LearningConfig": (
        "GET /v1/learning/status returns enabled/shadowMode/autoWeights/signalWeights/rewards "
        "only; autoWeightsOptions and syncIntervalMs are console write-side settings"
    ),
    "AutoWeightsOptions": "nested inside LearningConfig — console write-side only",
    "VoiceConfigInput": "console voice settings; the session planes read VoiceIceServer instead",
    "VoiceConfigView": "console voice read view; withholds the credential the session planes get",
    "VoiceIceServerInput": "the console's write shape for a relay; sessions read VoiceIceServer",
    "PolicyDefaults": "workspace policy defaults are set through the console plane",
}


def strip_comments(text: str) -> str:
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
    return re.sub(r"//.*", "", text)


def platform_types(explicit: str | None) -> Path | None:
    """Locate the reference SDK's types.ts, or None when this checkout cannot see it."""
    for candidate in (explicit, os.environ.get("FIVEXER_PLATFORM"), REPO.parent / "platform"):
        if not candidate:
            continue
        types = Path(candidate) / "packages" / "sdk" / "src" / "types.ts"
        if types.is_file():
            return types
    return None


def declarations(types_src: str) -> dict[str, str]:
    """Split types.ts into one body per exported interface/type, keyed by name."""
    starts = {m.group(1): m.start() for m in re.finditer(r"^export\s+(?:interface|type)\s+(\w+)", types_src, re.M)}
    ordered = sorted(starts, key=lambda n: starts[n])
    bodies = {}
    for i, name in enumerate(ordered):
        end = starts[ordered[i + 1]] if i + 1 < len(ordered) else len(types_src)
        bodies[name] = types_src[starts[name] : end]
    return bodies


def reachable(sdk_dir: Path, bodies: dict[str, str]) -> set[str]:
    """Types reachable from the three planes' clients, transitively.

    Deliberately excludes account-client.ts: the console/account plane is TypeScript-only, and
    pulling its types in would flag a hundred fields no other SDK is supposed to have.
    """
    seed = set()
    for filename in ("client.ts", "worker-client.ts", "supervisor-client.ts"):
        for word in re.findall(r"\b([A-Z]\w+)\b", strip_comments((sdk_dir / filename).read_text())):
            if word in bodies:
                seed.add(word)
    seen, queue = set(), list(seed)
    while queue:
        name = queue.pop()
        if name in seen:
            continue
        seen.add(name)
        for word in re.findall(r"\b([A-Z]\w+)\b", bodies[name]):
            if word in bodies and word not in seen:
                queue.append(word)
    return seen


def top_level_fields(body: str) -> list[str]:
    """Depth-1 members only — a nested inline object is a separate model in the other SDKs."""
    start = body.find("{")
    if start < 0:
        return []
    fields, depth = [], 0
    for line in body[start:].splitlines():
        match = re.match(r"^\s*(\w+)\??\s*:", line)
        if match and depth == 1:
            fields.append(match.group(1))
        depth += line.count("{") + line.count("[") - line.count("}") - line.count("]")
    return fields


def snake(name: str) -> str:
    return re.sub(r"(?<!^)(?=[A-Z])", "_", name).lower()


def load(globs: list[str]) -> dict[Path, str]:
    found = {}
    for pattern in globs:
        for path in REPO.glob(pattern):
            if path.is_file():
                found[path] = path.read_text(errors="ignore")
    return found


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--platform", help="path to the platform checkout")
    parser.add_argument("--verbose", action="store_true", help="also print what was classified")
    args = parser.parse_args()

    types_path = platform_types(args.platform)
    if types_path is None:
        print("platform checkout not reachable — skipping the field-parity check.")
        print("This is a skip, not a pass: run it where platform/ is available (see --platform).")
        return 0

    bodies = declarations(strip_comments(types_path.read_text()))
    covered = reachable(types_path.parent, bodies)

    python_src = "\n".join(load(["python/src/fivexer/*.py"]).values())
    java_files = load(["java/src/main/java/io/fivexer/sdk/**/*.java", "java/src/main/java/io/fivexer/sdk/*.java"])
    php_files = load(["php/src/**/*.php", "php/src/*.php"])

    def model_body(name: str) -> dict[str, str | None]:
        """The three SDKs' bodies for one model, preferring the model directory over a
        same-named resource class (Java and PHP both have a `Team` resource *and* a `Team`
        model, and picking the resource would report every field of the model as missing)."""
        py = re.search(rf"^class {re.escape(name)}\b.*?(?=\n@dataclass|\nclass |\Z)", python_src, re.S | re.M)

        def pick(files: dict[Path, str], marker: str) -> str | None:
            hits = [t for p, t in files.items() if p.stem == name]
            models = [t for p, t in files.items() if p.stem == name and marker in p.parts]
            return (models or hits or [None])[0]

        return {
            "python": py.group(0) if py else None,
            "java": pick(java_files, "model"),
            "php": pick(php_files, "Model"),
        }

    unclassified: list[str] = []
    gaps: list[tuple[str, str, list[str]]] = []
    classified: list[str] = []

    for name in sorted(covered):
        fields = top_level_fields(bodies[name])
        if not fields:
            continue
        if name in CONSOLE_PLANE_ONLY:
            classified.append(f"{name}: console-plane only — {CONSOLE_PLANE_ONLY[name]}")
            continue
        if name in AS_ARGUMENTS:
            classified.append(f"{name}: method arguments — {AS_ARGUMENTS[name]}")
            continue

        target = ALIAS.get(name, name)
        bodies_by_lang = model_body(target)
        if not any(bodies_by_lang.values()):
            unclassified.append(f"{name} (fields: {', '.join(fields)})")
            continue
        if name in ALIAS:
            classified.append(f"{name}: modelled as {target}")

        for field in fields:
            missing = [lang for lang, body in bodies_by_lang.items() if body is None]
            for lang, body in bodies_by_lang.items():
                if body is None:
                    continue
                needle = snake(field) if lang == "python" else field
                if not re.search(r"\b" + re.escape(needle) + r"\b", body):
                    missing.append(lang)
            if missing:
                gaps.append((target, field, sorted(set(missing))))

    print(f"reference types reachable from the three planes: {len(covered)}")
    if args.verbose:
        for line in classified:
            print(f"  classified  {line}")

    if unclassified:
        print(f"\n{len(unclassified)} reference type(s) with no model in any SDK, and no reason given:")
        for line in unclassified:
            print(f"   {line}")
        print("\nAdd each to ALIAS, AS_ARGUMENTS or CONSOLE_PLANE_ONLY in this script — with a")
        print("reason — or add the model to Python, Java and PHP.")

    if gaps:
        print(f"\n{len(gaps)} field(s) the reference SDK knows and another does not:")
        current = None
        for owner, field, missing in gaps:
            if owner != current:
                print(f"   -- {owner}")
                current = owner
            print(f"      {field:32s} missing in {', '.join(missing)}")

    if unclassified or gaps:
        return 1
    print("every reference field is present in Python, Java and PHP ✓")
    return 0


if __name__ == "__main__":
    sys.exit(main())
