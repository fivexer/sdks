# Enterprise SDKs for the Fivexer Platform `/v1` API (Python + Java)

> Status: implementation-ready plan. Scope: hand-written, MIT-public HTTP-client SDKs that wrap
> the platform's `/v1` API, published as 0.x beta to PyPI (Python) and Maven Central (Java). The
> existing `@fivexer/sdk` TS client is the reference implementation and the source of truth for
> the `/v1` contract.

---

## 1. Context (what exists, what we're building)

- **`assignment-user-matcher`** (this repo): MIT Node/Redis matching *engine* on npm. **Out of scope**
  for these SDKs — enterprises consume the *service*, not the embeddable engine.
- **`platform`** (`~/Projects/fivexer/platform`, private): Routing-as-a-Service. REST API `/v1`
  (tasks / workers / decisions / stats), `/auth`, `/console`, `/admin`. OpenAPI 3.1 spec at
  `docs/openapi.json` — **but `components.schemas` is `{}` (schemas are inline in `paths`)**.
- **`@fivexer/sdk`** (`platform/packages/sdk`, TS, v0.2.0, hand-written, zero-dep, **not yet
  published**): the reference. `Fivexer` class wraps `/v1`; `FivexerAccount` wraps `/auth`+`/console`
  (browser/dashboard only). We mirror the **`Fivexer` (`/v1`) surface** in each new language.

### `/v1` contract to mirror (authoritative: TS SDK + `docs/openapi.json`)

- **Base**: `{baseUrl}/v1`. **Auth**: `Authorization: Bearer sk_test_/sk_live_`.
- **tasks**: `POST /tasks` (create, Idempotency-Key), `GET /tasks` (list, paginated via
  `nextCursor`/`hasMore`), `GET /tasks/{id}`, `DELETE /tasks/{id}` (cancel),
  `POST /tasks/{id}/accept`, `POST /tasks/{id}/reject`, `POST /tasks/{id}/complete`.
- **workers**: `POST /workers` (upsert; id optional → server assigns), `GET /workers`,
  `GET /workers/{id}/queue`, `DELETE /workers/{id}`.
- **decisions**: `GET /decisions?taskId=&workerId=&limit=`.
- **stats**: `GET /stats`.
- **Quota headers** (any response, parsed onto `client.quota`): `x-quota-task-rate-limit`,
  `x-quota-task-rate-remaining`, `x-quota-queued-tasks-limit`, `x-quota-queued-tasks-remaining`,
  `x-quota-workers-limit`, `x-quota-workers-remaining`. `429`/`402` also carry `Retry-After`.
- **Error envelope**: `{ "error": { "code": "rate_limited|plan_limit_exceeded|…", "message": "…" } }`
  → language-native `FivexerApiError(status, code, message, quota)`.
- **Webhook signature** (helper to include): `X-Signature: t=<unix>,v1=HMAC-SHA256(secret,
  t + "." + rawBody)`; verify within ±5 min replay window.

### Resolved decisions (from planning interview)

1. SDKs wrap the **platform `/v1` HTTP API**, not the embedded engine.
2. **Python (PyPI) + Java (Maven Central) first**; expand to .NET/others later. Pure Java/JVM SDK
   is Kotlin-friendly and covers Android — **no separate Android/AAR artifact for v1** (defer a
   Kotlin-coroutines variant until asked).
3. **Hand-written, spec-as-contract** (no openapi-generator). OpenAPI spec is the validated
   contract via one shared contract-test suite run against every SDK + a real server.
4. New polyglot repo **`fivexer/sdks`** (`python/`, `java/`, `contract/`, later `typescript/`).
   The existing `platform/packages/sdk` stays put for now; relocating it into `sdks/` is a later,
   optional decision (it's flagged open in PLATFORM_PLAN.md §11).
5. **Publish 0.x beta now**; cut 1.0 across all SDKs when `/v1` is frozen to a stable contract.

---

## 2. Repo layout (`fivexer/sdks`, new, MIT)

```
fivexer/sdks/
  contract/                       # the single source of truth all SDKs are validated against
    openapi.json                  # synced FROM platform/docs/openapi.json (git-synced, not hand-edited here)
    fixtures/                     # language-agnostic request/response JSON + expected quota headers per endpoint
    cases.yaml                    # declarative contract cases (method, path, params, body, expected status/shape)
  python/                         # PyPI package `fivexer`
  java/                           # Maven group `io.fivexer`, artifact `fivexer-sdk`
  .github/workflows/              # per-language build/test/release matrices + contract-sync check
  LICENSE  (MIT)   README.md   CHANGELOG.md
```

**Contract-sync rule**: `contract/openapi.json` is a copy of `platform/docs/openapi.json` (kept in
sync by a CI job that fails if they diverge, or by a `scripts/sync-contract.sh` run from a
platform checkout). The SDKs are *hand-written* but the contract suite in §4 proves they match.

---

## 3. Shared SDK behavior (implement identically in every language)

Mirror `@fivexer/sdk`'s `Fivexer` class behavior exactly:

- Constructor: `{ baseUrl, apiKey, timeout?, maxRetries?, httpClient? }`. Strip trailing `/` from
  `baseUrl`. Sensible defaults: `timeout=30s`, `maxRetries=1`.
- Every request sends `Authorization: Bearer <apiKey>`, JSON body when present, `Accept: application/json`.
- **Retry**: on `429`/`5xx`, honor `Retry-After` (seconds) when present; one retry by default; never
  retry non-idempotent failures except 429.
- **`POST /tasks` idempotency**: auto-send `Idempotency-Key` header (UUID) unless caller supplies one.
- **Quota**: parse `X-Quota-*` on every response onto a mutable `client.quota`; attach to errors too.
- **Errors**: non-2xx → throw native `FivexerApiError(status, code, message, quota)`; 204 → empty/no body.
- **Resource API**: `client.tasks.{create,get,list,cancel,accept,reject,complete}`,
  `client.workers.{upsert,list,queue,remove}`, `client.decisions.list`, `client.stats()`.
  Same names and shapes as the TS SDK (`platform/packages/sdk/src/types.ts` is the projection).
- **Webhook helper**: `FivexerWebhook.constructEvent(rawBody, signatureHeader, secret, tolerance=300s)`
  → verified event or throws. Pure function, no network.
- **No `/auth` or `/console`**: those are browser/dashboard session surfaces, out of scope.

### Resource type projections (copy field-for-field from TS SDK `types.ts`)
`Task`, `TaskStatus`, `CreateTaskInput`, `TaskPage`, `ListTasksQuery`, `UpsertWorkerInput`,
`Decision`, `DecisionCandidate`, `ListDecisionsQuery`, `WorkspaceStats`, `QuotaInfo`. Numeric
timestamps are epoch-ms (`number`/`long`); `meta` is an arbitrary object map.

---

## 4. Contract test suite (the sync guarantee)

Because SDKs are hand-written, drift is caught by tests, not by a generator.

- `contract/cases.yaml`: one case per `/v1` endpoint × representative input (incl. an error case per
  error code: `rate_limited`, `plan_limit_exceeded`, `not_found`, validation `400`).
- Each SDK ships a **contract test** that, for each case: spins the request through the SDK against a
  **real `/v1` server** (the platform API, in test mode against a namespaced Redis + PGlite), and
  asserts status, parsed body shape, and quota-header parsing. Reuse the platform's existing
  real-Redis test harness (PLATFORM_PLAN.md §11 Phase 1) as the target server.
- The suite is the regression net: when the platform changes `/v1`, this fails first in CI, before
  any SDK release.

---

## 5. Python SDK (`python/`, PyPI)

- **Package name**: `fivexer`. **Python**: 3.9+. **HTTP**: `httpx` (sync + async clients —
  `Fivexer` and `AsyncFivexer`). **Serialization**: stdlib `json`; dataclasses for models
  (`Task`, `Worker`, `Decision`, …), hand-written (no pydantic hard dep — keep zero/low deps; optional
  pydantic-v2 integration can come later).
- **Project**: `pyproject.toml` (PEP 621), `hatchling` or `flit` build backend, `src/fivexer/` layout.
  `ruff` + `mypy` in CI. Tests: `pytest` + the contract suite.
- **Idiomatic niceties**: `FivexerApiError` subclasses a base exception; context-manager support
  optional; type stubs shipped with the package; `__repr__` redacts the key.
- **Publish (0.x beta)**: GitHub OIDC **trusted publishing** to PyPI (no long-lived tokens).
  Tag `python-v0.1.0` → workflow builds sdist+wheel → publishes. Mark classifiers `Development
  Status :: 4 - Beta`, `License :: OSI Approved :: MIT License`.

## 6. Java SDK (`java/`, Maven Central)

- **Coordinates**: `io.fivexer:fivexer-sdk:0.1.0`. **Java**: 11 bytecode target (8 acceptable if no
  records used; keep deps Android-friendly). **HTTP**: OkHttp 4 + Gson (or Moshi). **Async**:
  return `CompletableFuture<T>` from an `AsyncFivexer`; sync `Fivexer` blocks on it. (Android
  coroutines variant deferred.)
- **Build**: Gradle (Kotlin DSL), `maven-publish` plugin, sources + javadoc jars (Central requires
  both). JUnit 5 + the contract suite; `mockwebserver` for unit tests of transport/serialization.
- **Publish (0.x beta)**: **Maven Central Portal** (Central Publishing Portal, the current Sonatype
  route). Requirements: POM with `name/description/url/licenses/scm/developers`, GPG-signed artifacts
  (`signing` plugin), all on a registered namespace `io.fivexer` (claim once). Release via CI on tag
  `java-v0.1.0`. `maven-central-publishing` Gradle plugin + CI-held signing key + portal
  username/token (Central Portal doesn't yet support pure OIDC the way PyPI does — token in secrets).
- **Android note**: keep the jar JVM-only and ProGuard-friendly; document that it works on Android
  via Gradle `implementation`. Add `Consumer Rules` only if needed later.

---

## 7. CI / release pipeline

- `.github/workflows/`: `contract-sync.yml` (fail if `contract/openapi.json` != platform's),
  `python.yml` (ruff, mypy, pytest, contract), `java.yml` (gradle build, test, contract),
  `release-python.yml` (OIDC trusted publish on tag), `release-java.yml` (Central Portal publish on tag).
- **Versioning**: SDK version is independent per language but released together for a given API
  state. All start at `0.1.0`; bump per language. `CHANGELOG.md` notes the `/v1` API version each
  release targets.
- **Pre-publish gate**: contract suite green + a `dry-run` publish (PyPI test index / Central
  staging) on the release branch before tagging.

---

## 8. Ordered task list

1. **Create `fivexer/sdks` repo** (MIT `LICENSE`, `README.md` skeleton, this plan as `PLAN.md`),
   empty `python/`, `java/`, `contract/`, `.github/workflows/`.
2. **Seed `contract/`**: copy `platform/docs/openapi.json` → `contract/openapi.json`; write
   `cases.yaml` covering every `/v1` endpoint + one error case per error code; hand-write a few
   `fixtures/*.json`. Add `contract-sync.yml` (diff against platform path).
3. **Spec cleanup (in `platform`, not here)**: refactor `docs/openapi.json` inline schemas into
   `$ref`'d `components.schemas`. Strictly optional for hand-written SDKs, but it's the authoritative
   contract and unblocks any future codegen — do it as a platform PR. *(Can run parallel to step 4–7.)*
4. **Python SDK**: `src/fivexer/` — `client.py` (`Fivexer` + `AsyncFivexer`), `models.py`, `errors.py`,
   `webhook.py`, `__init__.py`. Mirror TS `Fivexer` behavior exactly. `pyproject.toml` (hatchling).
5. **Python contract test**: pytest harness targeting a real `/v1` server from `cases.yaml`.
6. **Python release path**: `release-python.yml` with OIDC trusted publishing; dry-run to TestPyPI;
   register the `fivexer` project on PyPI.
7. **Java SDK**: `java/` Gradle project — `Fivexer`, `AsyncFivexer`, model POJOs, `FivexerApiException`,
   `FivexerWebhook`, OkHttp transport. Mirror TS behavior. Sources + javadoc jars.
8. **Java contract test**: JUnit 5 harness targeting a real `/v1` server from `cases.yaml` + mockwebserver unit tests.
9. **Java release path**: register `io.fivexer` namespace on Central Portal; `release-java.yml`
   with GPG signing + portal credentials; dry-run to a staging repo.
10. **Docs**: per-language `README.md` (install + quickstart mirroring the TS README), one top-level
    quickstart, links from the platform docs site. Publish 0.1.0 to both registries.

---

## 9. Risks & mitigations

- **Drift between hand-written SDKs and the evolving `/v1` API** → the shared `contract/cases.yaml`
  suite, run against a real server, is the gate; nothing releases if it's red.
- **0.x churn frustrates early adopters** → clear beta labeling, CHANGELOG, and a stated 1.0 freeze
  condition (platform `/v1` stabilization).
- **Publishing credentials / bus factor** → OIDC trusted publishing for PyPI removes a secret; for
  Maven Central, store portal token + GPG key in GitHub secrets and document recovery in `RELEASE.md`.
- **OkHttp/Gson footprint on Android** → keep deps minimal and Java 11/8 bytecode; defer a dedicated
  Kotlin-coroutines artifact.
- **Spec inline schemas** → step 3 cleans it up; until then the TS SDK + `cases.yaml` are the de-facto
  contract.

---

## 10. Validation (definition of done for 0.1.0)

- A stranger can `pip install fivexer` and, with a `sk_test_` key against a sandbox, create a task,
  list it, and receive/verify a `task.matched` webhook — using only the SDK.
- A stranger can add the Maven artifact and do the same in Java (server or Android).
- Both SDKs pass the shared contract suite against a real `/v1` server in CI.
- `contract/openapi.json` is byte-identical to `platform/docs/openapi.json` (sync check green).

---

## 11. Out of scope (explicit)

- Native ports of the `assignment-user-matcher` *engine* in Java/Python.
- `.NET`, Go, Ruby SDKs (pattern is established; repeat per language on demand).
- A dedicated Android (Kotlin-coroutines / AAR) artifact — JVM jar covers mobile for now.
- SDK coverage of `/auth` + `/console` (browser/dashboard session surfaces).
- Relocating the existing `@fivexer/sdk` TS client into `sdks/` (later, optional).

## 11.1 In-scope but beta

A PHP SDK (`php/`) has been added to the repo ahead of the original plan and is documented as
beta. It covers the workspace-plane core (tasks, workers, decisions, stats, webhooks). Full
contract-suite parity, CI workflow, skills, workflows, notifications, and the worker-portal plane
are tracked as follow-up work before it graduates from beta.
