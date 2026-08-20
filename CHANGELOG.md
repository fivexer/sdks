# Fivexer SDKs Changelog

All notable changes to the Fivexer SDK packages are documented here. Releases are grouped by
language. The `/v1` API contract version each release targets is noted when relevant.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
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
  (PLAN.md §4) was never built. It is reported as a note until that runner lands.
- Java and PHP parity tests threw out of `substring`/`strrpos` on a dotless operation name
  (`portal`, `slaStats`), because only `stats` was special-cased. Both now resolve any dotless
  name as a top-level client method.

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
