# Fivexer SDKs Changelog

All notable changes to the Fivexer SDK packages are documented here. Releases are grouped by
language. The `/v1` API contract version each release targets is noted when relevant.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased] — task policies, recurring templates, worker attachments

Targets `/v1` as of 2026-08-28. Python `0.6.0`, Java `0.4.0`, PHP `dev-main`,
TypeScript `@fivexer/sdk` `0.30.0`.

### Added
- **The four task policies, in Python, Java and PHP.** `tasks.create` accepted `escalation`,
  `sla`, `schedule` and `recurrence` on the wire and in the TypeScript SDK; the other three
  dropped all four silently, along with `requiredSkills`, `teamId` and `preferTeamId`. A caller
  could set a response deadline in TypeScript and watch the same code in Python create a task
  with none. New models: `EscalationPolicy`, `SlaPolicy`, `SchedulePolicy`, `RecurrencePolicy`.
- **Absent versus null is now expressible.** Omitting a policy inherits the workspace default;
  an explicit null opts the task out of it. Every SDK prunes nulls from a request body, which is
  right for every other field and wrong for exactly these two — so each language grew a way to
  say "send null": `NULL_POLICY` in Python, `escalationNone()` / `slaNone()` in Java and PHP.
- **Recurring templates** (`tasks.recurring.list` / `.remove`) across all four SDKs, with the
  `RecurringTask` model and its clock (`nextAt`, `occurrences`). A template is created through
  `tasks.create` with a `recurrence` and is never itself matchable — it appears in neither
  `tasks.list` nor the queue stats, so this collection is the only view of standing work.
  `remove(id, dropScheduled)` decides the fate of occurrences already cut.
- **Worker-plane attachments** (`worker.attachments.create` / `.confirm` / `.list` /
  `.download`, plus the `upload` helper) in Python, Java and PHP — how an unattended agent hands
  over a deliverable as a file rather than a chunked comment thread. Two deliberate differences
  from the workspace plane: the uploader comes from the session, so `WorkerCreateAttachment` has
  no `workerId` to spoof, and there is no `remove` — a worker who could delete files could erase
  the evidence of their own work.
- **Voice ICE on both session planes** (`worker.voiceIce`, `supervisor.voiceIce`) in all four
  SDKs, including the reference TypeScript one, which was also missing them. **Experimental —
  voice is not production-ready**, and every model, method and catalogue entry says so. Fetched
  per call rather than cached (a TURN credential is short-lived, and a stale one fails once the
  call is already ringing); `voice_disabled` surfaces as a 404, never as an empty relay list,
  because "no voice on this workspace" and "no relay configured, go direct" are different
  outcomes and only one of them should let a call proceed.
- Response fields the three SDKs were dropping: `Task.skillThresholds` / `.escalation` /
  `.escalationLevel` / `.sla` / `.schedule` (the answer to "why is this task still queued?" and
  "where is it on the ladder?"); `WorkerDetail.learnedRoutingWeights` /
  `.routingWeightsSnapshot` / `.learnedRoutingWeightsSyncedAt` / `.teams` / `.invitePending` /
  `.pendingApproval`; `WorkerLoad.invitePending` / `.pendingApproval`;
  `WorkerSessionToken.expiresAt`. The three weight maps stay separate because collapsing them
  would make "an operator vetoed this tag" indistinguishable from "the model did".
- `UpsertWorker` gained `teamIds` (wholesale replacement; `[]` clears) and `available` (the
  escape hatch for programmatic fleets — without it a newly created agent worker is off shift
  and never matched).
- `learning.previewWeights` / `applyWeights` gained `overrideManual` and
  `includeUnexploredTags`. Both were live on the platform and reachable from TypeScript only.
  A flag left unset stays off the wire, so "I did not ask" remains distinguishable from a
  deliberate `false` — and a preview taken with different flags than the apply would be a
  preview of a different write.
- `contract/operations.yaml` gained the eight operations it had never claimed: the two recurring
  ones, four worker-plane attachment ones, and voice ICE on each session plane. The catalogue
  now accounts for every `/v1` operation in the spec — 150 implemented, 2 excluded, 0 unclaimed.
- **Payroll-grade working time, in the TypeScript SDK.** Correcting a recorded shift or break
  (`workers.correctTimeEntry`, and `workers.createTimeEntry` for one that was worked and never
  logged), the trail those changes leave (`workers.timeCorrections`), the priced period
  (`team.payroll`) and the close that makes a range of days final (`team.closures`,
  `team.closePeriod`, `team.reopenPeriod`, `team.closureCovering`). The eight operations are
  catalogued under `planned:` and their response types under `PLANNED`: they are workspace-plane
  wire, but the console is the only thing driving them so far, and a wage figure is the last
  shape to commit three hand-written SDKs to while it is still moving.
- Every time-log row now carries `id` and `corrected` in Python, Java and PHP as well —
  a wrong figure is noticed on a board and corrected by row, so without the id the caller would
  have to identify the record by the very times under dispute. The per-worker and portal reads
  also carry `correctionNote` / `correctedBy` / `correctedAt`: a person can always read the
  reason for a change to their own record, which is what keeps this a timesheet rather than
  surveillance.
- `RosterWorkerTerms.payrollId`, read and write — the workspace's worker id rarely matches what
  the payroll system calls the same person, and the export has to key straight in.
- **`scripts/check-field-parity.py`**, and a `contract-sync` job that runs it. The operation
  catalogue grades whether an endpoint has a method behind it and cannot see inside a body,
  which is exactly where this drift hid — `tasks.create` existed in all four SDKs for months
  while three of them dropped seven fields of its input, with every parity test green. The new
  gate walks the reference SDK's types and checks each field against Python, Java and PHP; a
  reference type with no counterpart must be classified as an alias, as method arguments, or as
  console-plane-only, *with a reason*, or the run fails.

### Changed
- **The Python SDK is now formatted, and the formatting is gated.** `ruff format --check` had
  never run anywhere, and 27 of 37 files had drifted — every one of them wrapped for a narrower
  line than `pyproject.toml`'s `line-length = 120`. The reformat is cosmetic (the suite reports
  the same 3394 statements, the same 8 misses and the same 99.55% coverage before and after),
  but it touches most of the package, so it lands as its own reviewable change. `ruff format
  --check src tests` is now a step in the `python` workflow beside `ruff check`, so the drift
  cannot silently return. Java and PHP have no formatter configured, so there is no equivalent
  gap to close there.

### Notes
- Voice remains **experimental** and is marked as such in every SDK's docs and in the operation
  catalogue. It is not production-ready; the surface may change or be withdrawn in a patch
  release.
- `LearningConfig.autoWeightsOptions` and `syncIntervalMs` are deliberately absent from the
  three hand-written SDKs. `GET /v1/learning/status` does not send them — they are console
  write-side settings on a TypeScript-only plane — so mirroring them would model a response
  nobody receives. Recorded in the new gate's `CONSOLE_PLANE_ONLY` table rather than left to be
  rediscovered.

## [Unreleased] — recorded working time

### Added
- **Recorded working time and per-worker analytics.** The platform now keeps a durable shift log
  beside the matcher's live availability state, so worked time — shift time minus overlapping
  breaks, since breaks are time *inside* a shift — is answerable historically rather than only
  for today. New operations across the ports: `workers.metrics` (one worker's today plus a rolling
  window), `workers.timeEntries` and the worker plane's own `worker.timeEntries` (the recorded
  shift/break log — the working-time record EU employers must keep, CJEU C-55/18), and
  `history.workerTimeseries` (one worker's buckets, where worked time and lifecycle counters ride
  only on `bucket=day` because both are day-grained).
- `history.workers` rows widened: `p50HandleMs`/`p95HandleMs`, the response counters
  (`offered`/`accepted`/`rejected`/`failed`/`expired`/`released` and `acceptanceRate`), worked
  time (`onShiftMs`/`breakMs`/`workingMs`/`shiftCount`) and `utilization`. The row set is now the
  union of archive, counters and shift log, so a worker who was on shift and finished nothing
  appears with zeros rather than going missing. `history.workers` and `team.presence` take
  `teamId`; `breaks.metrics` takes `teamId` and `workerId`.
- `worker.setAvailability` accepts `staleAfterMs` — a liveness contract for **unattended** workers
  only. It authorises the platform to clock the worker out after that much silence, which is what
  stops a crashed agent process reading as available forever. Interactive clients must never send
  it: a person working away from their phone is not a crashed process.
- `worker.metricsToday` gained `onShiftMs`/`shiftCount` (additive; absent on older servers).
- Top-level `sdks/README.md` now includes PHP, links to 5xer.com/docs, and documents the two
  credential planes.
- `php/README.md` with install, quickstart, error handling, webhooks, and beta status.
- `platform/packages/sdk/README.md` with TypeScript install, quickstart, and worker portal sample.
- `scripts/sync-contract.py`: copies the platform's generated spec into `contract/openapi.json`
  and grades `operations.yaml` against it. `--check` fails on drift, `--gaps-only` runs without
  a platform checkout.
- `contract/operations.yaml` gained `excluded:` (paths that serve HTML and will never have an SDK
  method) and `planned:` (tracked gaps) sections, so the gate can tell a decision from an
  oversight. The supervisor plane — 11 operations, a third credential type — is recorded there.

### Fixed
- **`contract/openapi.json` had drifted 58 operations behind the platform** (82 catalogued vs 140
  live). The `contract-sync` workflow claimed in its header to fail on exactly this, but never
  fetched the platform spec — it only checked the stale snapshot against itself. It now runs the
  drift check where the spec is reachable and says so plainly when it is not, instead of
  reporting a pass it did not earn.
- The `cases.yaml` completeness check no longer blocks a merge. Nothing reads that file: all four
  SDK parity tests walk `operations.yaml`, and the real-server contract runner it was written for
  was never built. It is reported as a note until that runner lands — see `contract/cases.yaml`.
- Java and PHP parity tests threw out of `substring`/`strrpos` on a dotless operation name
  (`portal`, `slaStats`), because only `stats` was special-cased. Both now resolve any dotless
  name as a top-level client method.

## [Unreleased] — supervisor plane

### Added
- **The supervisor plane, in all four SDKs.** A third credential type (`sv_` session) alongside
  the workspace key and the worker token, for a crew lead who watches and unblocks work rather
  than doing it: `entry`, `acceptInvite`, `logout`, `me`, `overview`, `unpark`, `setPriority`,
  `assign`, `setAvailability`, and Web Push. New clients: `FivexerSupervisor` in TypeScript,
  Java and PHP; `FivexerSupervisor` / `AsyncFivexerSupervisor` in Python.
- `contract/operations.yaml` now declares the `supervisor` plane and its 12 operations, so all
  four `ContractParityTest`s hold the new clients to it. The `planned:` section is empty.

### Notes
- `SupervisorSession.expiresAt` is **epoch-milliseconds**, not the ISO-8601 string the worker
  plane sends. The two planes genuinely differ on the wire and the clients mirror that rather
  than papering over it.
- Subscribing to supervisor push answers `{ ok: true }`, not the `{ endpoint, createdAt }` the
  worker plane returns — that table is keyed by endpoint and has nothing else to hand back.
- There is no supervisor `refresh`. A session is redeemed from a single-use link and, once
  expired, can only be replaced by a new link; the clients deliberately have no recovery path,
  because inventing one would hide that.

### Fixed
- `GET /v1/supervisor-auth/entry` was mis-classified as an HTML page in the `excluded:` section.
  It returns JSON (`{ consoleUrl }`) and is a real SDK operation.
- CI ran the Windows matrix legs under PowerShell, which cannot parse `if grep …` — and, worse,
  propagates only the **last** command's exit code in a multi-line `run:`, so a failing test
  suite followed by a passing gate script reported success. All three workflows now pin
  `shell: bash` on their test jobs. The skipped-test guard greps source, so it runs on one leg.
- PHPUnit is invoked as `php vendor/phpunit/phpunit/phpunit` rather than through the
  `vendor/bin` proxy, which on Windows is a shell script beside a `.bat`.

## [java-0.2.0]

### Added
- **45 operations, reaching parity with the shared contract catalogue.** Teams
  (`client.teams()`), QR join links (`client.joinLinks()`), worker identities and invites
  (`client.identities()`), bulk create and the dry-run check, the escalation ladder
  (`ack`/`escalate`/`parked`/`scheduled`/`unpark`), `slaStats()`, `queueAudit()`, `portal()`,
  `skills().get()`, and `learning().revertWeights()`.
- The worker portal's self-service plane on `FivexerWorker`: `refresh`, `join`, `acceptInvite`,
  `me`, `setAvailability`, `changePin`, `skillCatalog`, `setSkills`, `comments`, `addComment`,
  `metricsWindow`, `updateLocation`, Expo device registration, and Web Push.
- `UpdateWorkerIdentity.clearEmail()` — Gson omits nulls, which is right everywhere else, so
  erasing a nullable field needs its own flag and a hand-built body.

### Fixed
- `FivexerWorker`'s transport had no way to send a query string; no worker operation had needed
  one until this release.
- The parity test threw out of `substring()` on any operation name without a dot, because only
  `stats` was special-cased. Dotless names now resolve as top-level client methods.

## [php-0.2.0]

### Added
- The same 45 operations as Java above: `teams()`, `joinLinks()`, `identities()`, bulk create,
  the dry-run check, the escalation ladder, `slaStats()`, `queueAudit()`, `portal()`,
  `skills()->get()`, `learning()->revertWeights()`, and the worker self-service plane.
- The beta caveat in `php-0.1.0` no longer applies: skills, workflows, notifications and the
  worker-portal plane are all implemented.

### Fixed
- **Empty request bodies went out as `[]` rather than `{}`.** PHP has one array type, so
  `json_encode([])` emits an array and a Fastify body schema rejects it. This already affected
  `learning()->applyWeights()` with no worker ids before this release; every endpoint whose body
  is entirely optional would have 400'd.
- `FivexerWorker`'s transport had no query-string support, same as Java.
- The parity test mis-parsed dotless operation names: `(int) strrpos(...)` turned `false` into
  `0` rather than signalling "no dot".

## [python-0.3.0]

### Added
- **45 operations, closing the gap to the TypeScript reference SDK.** Teams (`client.teams`),
  QR join links (`client.join_links`), worker identities and invites (`client.identities`),
  bulk create and the dry-run check (`tasks.create_many` / `tasks.check`), the escalation ladder
  (`tasks.ack` / `escalate` / `parked` / `scheduled` / `unpark`), `client.sla_stats()`,
  `client.queue_audit()`, `client.portal()`, `skills.get`, and `learning.revert_weights`.
- The worker portal's self-service plane on `FivexerWorker`: `refresh`, `join`, `accept_invite`,
  `me`, `set_availability`, `change_pin`, `skill_catalog`, `set_skills`, `comments`,
  `add_comment`, `metrics_window`, `update_location`, Expo device registration, and Web Push
  (`push_config` / `push_subscribe` / `push_unsubscribe`).
- `CLEAR` sentinel for fields that are genuinely nullable on the wire (an identity's email), where
  absent and null mean different things and `compact()` alone cannot express "erase this".

### Fixed
- The worker-plane transport dropped `spec.query` entirely. No worker operation needed a query
  string until this release; paging worker task comments would have silently re-read page one.

## [python-0.2.0]

### Added
- Full Python SDK for the `/v1` workspace and worker-portal planes: sync `Fivexer`, async
  `AsyncFivexer`, sync/async `FivexerWorker`/`AsyncFivexerWorker`.
- Idiomatic dataclass models (`CreateTask`, `UpsertWorker`, `Decision`, `WorkflowDefinition`, etc.).
- Quota header parsing, automatic `Idempotency-Key` on `POST`, retry on `429`/`5xx` honoring
  `Retry-After`.
- Webhook signature verification via `Webhook.construct_event`.

## [java-0.1.0]

### Added
- Java SDK for the `/v1` workspace and worker-portal planes.
- OkHttp + Gson transport, `Fivexer` and `FivexerWorker` clients.
- Quota parsing, automatic idempotency, retry, and webhook verification helpers.

## [php-0.1.0] — beta

### Added
- PHP workspace-plane core: tasks, workers, decisions, stats, and webhooks.
- Guzzle 7 transport, `FivexerApiException`, `Webhook::constructEvent`.
- Beta label: skills, workflows, notifications, and the worker-portal plane are not yet
  implemented.
