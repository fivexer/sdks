#!/usr/bin/env python3
"""Sync contract/openapi.json from the platform repo, and report SDK parity gaps.

`contract/openapi.json` is a *copy* of the platform's generated `docs/openapi.json`. Because
the platform repo is private, CI in this repo cannot fetch it unaided — so drift detection
only runs where the spec is reachable (a local checkout, or an authenticated CI job). The
self-consistency checks below need no platform access and always run.

Usage:
    scripts/sync-contract.py                 # copy the spec in, then report gaps
    scripts/sync-contract.py --check         # fail if the snapshot is stale (needs platform)
    scripts/sync-contract.py --gaps-only     # report gaps against the current snapshot
    scripts/sync-contract.py --platform DIR  # explicit platform checkout

Platform location resolves in this order: --platform, $FIVEXER_PLATFORM, ../platform.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
SNAPSHOT = REPO / "contract" / "openapi.json"
CASES = REPO / "contract" / "cases.yaml"
OPERATIONS = REPO / "contract" / "operations.yaml"
METHODS = ("get", "post", "put", "patch", "delete")


def platform_spec_path(explicit: str | None) -> Path | None:
    """Locate the platform's generated spec, or None when this checkout cannot see it."""
    for candidate in (explicit, os.environ.get("FIVEXER_PLATFORM"), REPO.parent / "platform"):
        if not candidate:
            continue
        spec = Path(candidate) / "docs" / "openapi.json"
        if spec.is_file():
            return spec
    return None


def normalise(path: str) -> str:
    """`/v1/tasks/{id}/comments/{commentId}` -> `/v1/tasks/{}/comments/{}`.

    Parameter *names* are an SDK-authoring choice; only the shape is contractual.
    """
    return re.sub(r"\{[^}]*\}", "{}", path)


def v1_operations(spec: dict) -> set[tuple[str, str]]:
    return {
        (method.upper(), normalise(path))
        for path, item in spec["paths"].items()
        if path.startswith("/v1")
        for method in item
        if method.lower() in METHODS
    }


def catalogued_operations() -> set[tuple[str, str]]:
    """(METHOD, path) pairs declared in operations.yaml, parsed without a YAML dependency.

    Entries are `- name:` blocks carrying a `method:` and a `path:`; both always appear, and
    a new block always resets the pair, so a malformed entry cannot silently inherit.
    """
    pairs: set[tuple[str, str]] = set()
    base = re.search(r"^base_path:\s*(\S+)", OPERATIONS.read_text(encoding="utf-8"), re.M).group(1)
    method: str | None = None
    for line in OPERATIONS.read_text(encoding="utf-8").splitlines():
        if re.match(r"\s*- name:", line):
            method = None
        elif m := re.match(r"\s*method:\s*(\S+)", line):
            method = m.group(1).upper()
        elif (m := re.match(r"\s*path:\s*(\S+)", line)) and method:
            pairs.add((method, normalise(base + m.group(1))))
            method = None
    return pairs


def declared_non_sdk() -> tuple[set[tuple[str, str]], set[tuple[str, str]]]:
    """(excluded, planned) operations declared in operations.yaml's trailing sections.

    Both are *decisions*, recorded so the gate can tell a deliberate omission from a forgotten
    endpoint. `excluded:` is permanent (the path serves HTML, so no SDK will ever wrap it);
    `planned:` is a tracked gap that should shrink. An operation in neither is a bug.
    """
    text = OPERATIONS.read_text(encoding="utf-8")

    def block(header: str) -> str:
        if f"\n{header}:" not in text:
            return ""
        rest = text.split(f"\n{header}:", 1)[1]
        # Sections run to the next top-level key; everything below is indented under this one.
        return re.split(r"\n(?=[a-z_]+:)", rest)[0]

    excluded: set[tuple[str, str]] = set()
    for entry in re.finditer(r"- path:\s*(\S+)\s*\n\s*methods:\s*\[([^\]]*)\]", block("excluded")):
        for method in entry.group(2).split(","):
            excluded.add((method.strip().upper(), normalise(entry.group(1))))

    planned = {
        (m.group(1).upper(), normalise(m.group(2)))
        # `.+?` up to the line's closing brace, not `[^}]+`: paths carry `{id}` themselves.
        for m in re.finditer(r"-\s*\{\s*method:\s*(\w+),\s*path:\s*(.+?)\s*\}\s*$", block("planned"), re.M)
    }
    return excluded, planned


def cased_paths() -> set[str]:
    text = CASES.read_text(encoding="utf-8")
    base = re.search(r"^base_path:\s*(\S+)", text, re.M).group(1)
    return {base + p.split("?", 1)[0] for p in re.findall(r"(?<![\w-])path:\s*(/\S+)", text)}


def path_has_case(spec_path: str, referenced: set[str]) -> bool:
    """Cases write concrete ids (`/v1/tasks/task_8fk2`); the spec writes templates."""
    pattern = re.compile("^" + re.sub(r"\\\{[^}]+\\\}", "[^/]+", re.escape(spec_path)) + "$")
    return any(pattern.match(r) for r in referenced)


def report_gaps(spec: dict) -> int:
    ops = v1_operations(spec)
    implemented = catalogued_operations()
    excluded, planned = declared_non_sdk()
    missing_ops = sorted(ops - implemented - excluded - planned)

    print(f"/v1 operations in spec: {len(ops)}")
    print(
        f"  implemented: {len(ops & implemented)}   "
        f"planned: {len(ops & planned)}   excluded (not SDK surface): {len(ops & excluded)}"
    )

    if missing_ops:
        print(f"\noperations.yaml accounts for neither — {len(missing_ops)} unclaimed operation(s):")
        for method, path in missing_ops:
            print(f"   {method:6} {path}")
        print("\nAdd an entry under `groups:` (implemented), `planned:`, or `excluded:`.")
    else:
        print("every /v1 operation is accounted for ✓")

    if planned:
        print(f"\nknown gap — {len(planned)} operation(s) declared `planned:` and not yet in any SDK:")
        for method, path in sorted(planned):
            print(f"   {method:6} {path}")

    # cases.yaml is graded but does not block. Nothing reads it yet: all four SDK parity tests
    # walk operations.yaml, and the real-server contract runner it was written for (PLAN.md §4)
    # has never been built. Gating a merge on completeness nobody consumes is the same failure
    # this workflow was fixed for — a check that asserts more than it verifies. Make it blocking
    # the day that runner lands.
    referenced = cased_paths()
    missing_cases = sorted(p for p in spec["paths"] if p.startswith("/v1") and not path_has_case(p, referenced))
    if missing_cases:
        print(f"\nnote: cases.yaml documents {len(missing_cases)} fewer /v1 path(s) than the spec.")
        print("      Not blocking — no test reads cases.yaml yet (see PLAN.md §4).")
    else:
        print("every /v1 path has a contract case ✓")

    return 1 if missing_ops else 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--platform", help="path to a platform checkout")
    parser.add_argument("--check", action="store_true", help="fail on drift instead of syncing")
    parser.add_argument("--gaps-only", action="store_true", help="skip the platform copy entirely")
    args = parser.parse_args()

    if args.gaps_only:
        return report_gaps(json.loads(SNAPSHOT.read_text(encoding="utf-8")))

    source = platform_spec_path(args.platform)
    if source is None:
        print("::notice::platform spec not reachable — drift NOT verified, checking snapshot only")
        return report_gaps(json.loads(SNAPSHOT.read_text(encoding="utf-8")))

    upstream = json.loads(source.read_text(encoding="utf-8"))
    current = json.loads(SNAPSHOT.read_text(encoding="utf-8")) if SNAPSHOT.is_file() else None

    if current != upstream:
        added = sorted(v1_operations(upstream) - v1_operations(current or {"paths": {}}))
        removed = sorted(v1_operations(current or {"paths": {}}) - v1_operations(upstream))
        if args.check:
            print(f"contract/openapi.json is STALE against {source}")
            for method, path in added:
                print(f"   + {method:6} {path}")
            for method, path in removed:
                print(f"   - {method:6} {path}")
            print("\nRun scripts/sync-contract.py from a platform checkout to refresh it.")
            return 1
        SNAPSHOT.write_text(json.dumps(upstream, indent=2) + "\n", encoding="utf-8")
        print(f"synced contract/openapi.json from {source} (+{len(added)} / -{len(removed)} operations)")
    else:
        print(f"contract/openapi.json is in sync with {source} ✓")

    return report_gaps(upstream)


if __name__ == "__main__":
    sys.exit(main())
