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

#: Genuinely on a workspace-plane wire, genuinely not modelled in Python, Java or PHP yet.
#: The counterpart of `planned:` in operations.yaml, and it carries the same meaning: a debt,
#: not a decision, so it is reported on every run and must shrink. It does NOT belong to
#: CONSOLE_PLANE_ONLY — these responses really are sent to a workspace key, and filing them
#: there would record a false reason for the omission.
PLANNED = {
    # Agent runs (platform doc 86, 2026-10-10). The TypeScript SDK and the MCP run tools carry
    # them first; bound in Python, Java and PHP once the ledger shape has settled with the
    # canvas and save-as-workflow plans that read it.
    "AgentRun": "GET/POST /v1/runs — a goal an agent pursues through 5xer, recorded with its reasons",
    "AgentRunOutcome": "nested in AgentRun — how the run ended",
    "RunBlocker": "nested in AgentRun — the question a blocked run waits on",
    "RunDecisionInput": "POST /v1/runs/{id}/decisions — a choice the agent made, and why",
    "RunEvent": "nested in RunEventsPage — one recorded step of a run",
    "RunEventsPage": "GET /v1/runs/{id}/events — a run's record after a seq, optionally long-polled",
    "StartAgentRunInput": "POST /v1/runs — the goal, its key and its inputs",
    "SaveRunAsWorkflowResult": "POST /v1/runs/{id}/workflow — the workflow a run became, and its gaps",
    "WorkflowCompileGap": "nested in SaveRunAsWorkflowResult — what a run's record cannot vouch for",
    "RunTask": "GET /v1/runs/{id}/tasks — a task as a run's record knows it",
    "TaskRunLink": "GET /v1/tasks/{id}/run — the run a task was part of",
    # Agents as executors (platform doc 87, 2026-10-10). The TypeScript SDK and the MCP tools
    # (who_can_help, plain-language delegate) carry them first.
    "Executor": "nested in WhoCanHelp — one person or agent, their skills, and whether they are online",
    "SkillAvailability": "nested in WhoCanHelp — who holds a skill and how many are online",
    "WhoCanHelp": "GET /v1/executors — who can do what right now",
    "NamedSkill": "nested in CreateAgentLinkInput — a skill named in plain words, with a level",
    "CreateAgentLinkInput": "POST /v1/agent-links — the agent's name, owner and skills",
    "AgentLinkResult": "POST /v1/agent-links — the one-line connect command, shown once",
    "WorkerAssistant": "GET /v1/portal/me/assistants — the worker portal's own read; the portal has its own client",
    "HostedAgent": "GET/POST /v1/hosted-agents — an agent running at Anthropic or OpenAI, connected as a worker",
    "ConnectHostedAgentInput": "POST /v1/hosted-agents — the provider, its key, the agent's id and its skills",
    # Leave and absences read by operators. Same caution as the payroll surface below: wage-adjacent
    # shapes, bound in three SDKs once they stop moving.
    "AbsenceItem": "nested in WorkerAbsencesPage — one absence, synced or local",
    "WorkerAbsencesPage": "GET /v1/workers/{id}/absences — a worker's absences, paged",
    "WorkerAbsencesQuery": "the window and cursor on GET /v1/workers/{id}/absences",
    "DepartedLeaveSettlement": "POST /v1/workers/{id}/settle-leave — leave settled after removal",
    "UnsettledLeaver": "GET /v1/workers/leave-settlements — a removed person whose leave is not settled",
    "LeavePayPortion": "nested in PayrollWorker — one portion of leave pay and who pays it",
    # The live-ops overview. An operator screen's read, polled by the console; bound in
    # three SDKs once the shape has lived through a few releases of the page it serves.
    "LiveOverview": "GET /v1/stats/live — the queue by lane, flow, every worker's state, post cover",
    "LiveLane": "nested in LiveOverview — one routing tag's share of the queue and who can take it",
    "LiveWorker": "nested in LiveOverview — one worker's state right now",
    "LiveSite": "nested in LiveOverview — one site's people and posts",
    "LivePost": "nested in LiveSite — one post's cover right now",
    "LiveFlowMinute": "nested in LiveOverview — one minute of arrivals and departures",
    "LiveLaneTasks": "GET /v1/stats/live/lanes/{tag} — the oldest queued tasks in one lane",
    # Payroll analysis and statutory figures. The wage-figure caution on the rest of the
    # payroll surface applies: bound in three SDKs once the shapes stop moving.
    "PayrollAnalysis": "GET /v1/team/time/payroll/analysis — the ledger grouped",
    "PayrollAnalysisQuery": "the filter on GET /v1/team/time/payroll/analysis",
    "PayrollLedgerTotal": "nested in PayrollAnalysis — one group's totals",
    "PeriodStatutoryRecord": "GET /v1/team/time/closures/{closureId}/statutory — a closed period's figures",
    "PeriodStatutorySummary": "nested in a closure — how many figures were computed or blocked",
    "StatutoryLedgerEntry": "nested in WorkerStatutoryNet — one step of the gross-to-net",
    "WorkerStatutoryNet": "nested in PeriodStatutoryRecord — one person's gross-to-net",
    # Payroll: closing the range of days that makes the record final, and the
    # priced figures. Correcting a record is bound in all four SDKs; these wait —
    # a wage figure is the last shape to commit three hand-written SDKs to while
    # it is still moving.
    "TimePeriodClosure": "POST /v1/team/time/close — a range of days signed off for pay",
    "ClosePeriodInput": "the body that signs a period off",
    "ListClosuresQuery": "the filter on GET /v1/team/time/closures",
    "PayrollQuery": "the period on GET /v1/team/time/payroll",
    "PayrollResult": "GET /v1/team/time/payroll — recorded hours priced and set against the rota",
    "PayrollWorker": "nested in PayrollResult — one person's period, priced",
    "PayrollShift": "nested in PayrollWorker — one recorded shift, priced and matched",
    "PayrollMissedShift": "nested in PayrollWorker — rostered, nothing recorded against it",
    "PayrollExceptions": "nested in PayrollResult — the questions a manager settles before closing",
    # The pay model the payroll figures are built on. Reachable from the three
    # planes only because PayrollResult carries them; the rota-side report that
    # owns them (GET /v1/roster/planned-hours) is itself `planned:`, so these
    # bind when it does.
    "RosterPayBand": "nested in PayrollResult — the brackets the minutes were sorted into",
    "PlannedHoursResult": "GET /v1/roster/planned-hours — the rota-side half of the same arithmetic",
    "PlannedWorkerHours": "nested in PlannedHoursResult; PayrollWorker extends it",
    "PlannedShiftBreakdown": "nested in PlannedWorkerHours — one planned shift's minutes by bracket",
    "PlannedPositionHours": "nested in PlannedWorkerHours — one rate a person was paid at over the period, from the rate card",
    "RoleStretchInput": "PUT /v1/workers/{id}/time-entries/{entryId}/roles — a manager stating which role part of a shift was worked in",
    "RoleStretch": "nested in WorkerTimeEntry — one stretch of a shift spent in one role, with the role's name",
    "SetTimeEntryRolesInput": "PUT /v1/workers/{id}/time-entries/{entryId}/roles — the roles and the reason",
    "SetTimeEntryRolesResult": "PUT /v1/workers/{id}/time-entries/{entryId}/roles — the roles as stored, and the correction that records them",
    "WorkerRoleChoice": "nested in WorkerRolesResult — a role a worker can clock in as",
    "WorkerRolesResult": "GET /v1/portal/me/roles — the worker portal's own read; the portal has its own client",
    "SwitchRoleResult": "POST /v1/portal/me/role — the worker portal's own write; the portal has its own client",
    "PlannedDayHours": "nested in PlannedHoursResult — the day series the planning view charts",
    # Worker schedule preferences (wishes). Workspace and portal wire types,
    # tracked alongside GET/PUT /v1/roster/terms/{workerId}/preferences and
    # GET/PUT /v1/portal/preferences + GET /v1/portal/preferences/outcome in
    # contract/operations.yaml — console/portal-plane authoring, no hand-written
    # SDK consumes them yet.
    "WorkerPreferences": "the wish document itself: week days, shift types and dated wishes",
    "DatedWish": "nested in WorkerPreferences — one dated wish (a day off, or in)",
    "WishOutcome": "GET /v1/portal/preferences/outcome — one wish, restated against what happened",
    "PortalPreferences": "GET/PUT /v1/portal/preferences — the worker portal's own read/write shape",
    "PortalPreferencesOutcome": "GET /v1/portal/preferences/outcome — the period's wishes, resolved",
    "PortalWishConfirmation": "POST /v1/portal/preferences/confirm — the periods a \"nothing has changed\" answered",
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
    # Rostering is dual-mounted on the console plane only for now. It is a
    # supervisor's authoring surface — draft a period, lint it, solve it,
    # publish it — read by the console and, for the worker-facing half, by the
    # portal's own client. Committing four hand-written SDKs to the shapes
    # before they have settled is the cost this defers; when the surface stops
    # moving these move to operations.yaml and the other three SDKs together.
    "RosterSettings": "console rostering settings; the workspace's own working-time rules",
    "RosterCycle": "console planning cycle; roster autopilot settings",
    "RosterCycleConfig": "console planning cycle cadence and deadlines",
    "RosterCycleAutonomy": "console planning cycle autonomy level",
    "RosterCycleIssue": "console planning cycle validation issue",
    "RosterCyclePreview": "console planning cycle date preview",
    "SaveRosterCycleInput": "console write shape for a planning cycle",
    "RosterCycleRun": "console planning cycle run",
    "RosterCycleEvent": "console planning cycle log entry",
    "RosterCycleSubmissions": "console planning cycle run; who of the crew has answered the wish window",
    "RosterCycleRemindResult": "console planning cycle run; the manual wishes reminder",
    "RosterPublishPolicy": "console planning cycle; when a planned rota may go out on its own",
    "RosterCoverPolicy": "console planning cycle; how a sick call is offered in waves and escalated",
    "RosterSwapPolicy": "console planning cycle; when a worker swap needs a manager's yes",
    "RosterTimeOffPolicy": "console planning cycle; when a leave request approves itself",
    "TimeOffNoteReading": "console time off; what the decisions service read in a leave note",
    "RosterPublishBlockReason": "console planning cycle run; why a rota could not go out on its own",
    "RosterCycleBlock": "console planning cycle run; a blocked run's reasons and diagnosis",
    "RosterPostedOpenShift": "console planning cycle run; an empty seat a publish offered to the team",
    "RosterCycleApproveResult": "console planning cycle run; a manager's approve",
    "RosterHolidayLocation": "console rostering; a country or region the holiday calendar covers",
    "RosterHoliday": "console rostering; one public holiday the calendar resolved",
    "RosterContractHours": "console rostering; planned against contracted hours, from the engine",
    "UpdateRosterSettingsInput": "console write shape for rostering settings",
    "WorkingTimeRules": "caller-supplied rule set, passed through opaquely — the engine owns its shape",
    "ShiftTemplate": "console shift-template library",
    "CreateShiftTemplateInput": "console write shape for a shift template",
    "UpdateShiftTemplateInput": "console write shape for a shift template",
    "Roster": "console rostering; a planned period",
    "RosterDetail": "console rostering; the roster with its expanded grid",
    "CreateRosterInput": "console write shape for a draft roster",
    "RosterAssignment": "console rostering; one planned pair",
    "ShiftInstance": "console rostering; a dated occurrence the grid draws",
    "RosterIssue": "console rostering; a rule verdict flattened for the lint panel",
    "RosterIssueSeverity": "console rostering; the lint panel's three levels",
    "RosterLintSummary": "console rostering; violation counts by severity",
    "RosterLintResult": "console rostering; the instant-feedback response",
    "RosterRuleVerdict": "console rostering; one rule's judgement on one pair",
    "RosterCandidate": "console rostering; who could take a shift, with blockers",
    "RosterDiagnosis": "console rostering; pre-solve capacity findings",
    "RosterDiff": "console rostering; what a publish changes for people",
    "RosterPublishPreflight": "console rostering; what publishing would change",
    "RosterVersionSummary": "console rostering; the version history",
    "SetRosterAssignmentsInput": "console write shape for a roster's assignments",
    "SetRosterAssignmentsResult": "console rostering; the version plus its lint",
    "RosterSolveJob": "console rostering; a background solve",
    "RosterSolveStatus": "console rostering; a solve job's state",
    "RosterStatus": "console rostering; draft/published/archived",
    "TimeOffRequest": "console + portal time off; the portal has its own client",
    "TimeOffStatus": "console + portal time off; request lifecycle",
    "TimeOffKind": "console + portal time off; the label on a request",
    "CreateTimeOffInput": "console write shape for a time-off request",
    "TimeOffQuery": "console filter for time-off requests",
    "TimeOffImpact": "console rostering; what approving a request would cost",
    # Capacity radar (spec §5-§6): dual-mounted on the console plane only, same
    # reasoning as the rest of rostering above — the console is the only
    # authoring surface today, and committing three more SDKs to a shape that
    # is still settling is the cost this defers.
    "CapacityAlertTier": "console capacity radar; watch/at-risk/critical severity",
    "CapacitySkillResilience": "console capacity radar; one skill's shortfall on a judged shift",
    "RadarActionKind": "console capacity radar; the kind of suggested action",
    "RadarAction": "console capacity radar; one suggested action to close a shortfall",
    "CapacityAlertDetails": "console capacity radar; an alert's aggregate detail, never individual identity",
    "CapacityAlert": "console capacity radar; one alert on a published shift",
    "CapacityRadarShift": "console capacity radar; one judged shift over the 72h window",
    "CapacityRadar": "console capacity radar; GET /capacity/radar",
    "CapacityLaneProjection": "console capacity radar; one routing lane's projected backlog",
    "CapacityToday": "console capacity radar; GET /capacity/today",
    "CapacitySettings": "console capacity radar; GET/PUT /capacity/settings",
    "CapacityEventScope": "console capacity radar; sites/teams/templates an event touches",
    "CapacityEventEffect": "console capacity radar; a percent, headcount, or closed effect",
    "CapacityEventInput": "console write shape for a capacity event",
    "CapacityEvent": "console capacity radar; a known future busy or closed period",
    "OverrideProposal": "console capacity radar; one shift template's proposed demand override",
    "CapacityAcceptEventResult": "console capacity radar; POST /capacity/events/{id}/accept",
    "CapacityPrecision": "console capacity radar; GET /capacity/precision",
    "CapacityReserve": "console capacity radar; standby answers on GET /capacity/alerts",
    "CapacityReserveStatus": "console capacity radar; standby answer status",
    "CapacityStandbyResult": "console capacity radar; POST /capacity/alerts/{id}/standby",
    "TimeOffCapacityRow": "console rostering; the capacity half of GET /timeoff/{id}/impact",
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
    planned_gap: list[str] = []
    gaps: list[tuple[str, str, list[str]]] = []
    classified: list[str] = []

    for name in sorted(covered):
        fields = top_level_fields(bodies[name])
        if not fields:
            continue
        if name in PLANNED:
            planned_gap.append(f"{name} — {PLANNED[name]}")
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
        print("\nAdd each to ALIAS, AS_ARGUMENTS, CONSOLE_PLANE_ONLY or PLANNED in this script")
        print("— with a reason — or add the model to Python, Java and PHP.")

    if planned_gap:
        print(f"\nknown gap — {len(planned_gap)} type(s) declared `PLANNED` and not yet modelled in Python, Java or PHP:")
        for line in planned_gap:
            print(f"   {line}")

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
