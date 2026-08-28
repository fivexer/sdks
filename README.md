# Fivexer SDKs

Official client libraries for **Fivexer** — task routing that learns who's best, and never
breaks your rules.

Fivexer routes each ticket, case, or field job to whoever is actually best for it, by skill and
current load: queues clear faster, nobody drowns while others sit idle, and every decision is
traceable to a name and a reason. These SDKs are how your application talks to it — describe the
work, describe who can do it, and the platform assigns continuously while webhooks tell you what
happened.

You set the rules; the engine does the assigning. Certifications and capacity are checked
before anything is assigned — no exceptions — and the learning only ever ranks candidates who
already passed. Someone who must not take a job never gets it, whatever the model prefers, and
the decision trail says who was considered and why.

**[Product](https://5xer.com)** · **[Docs](https://5xer.com/docs)** ·
**[How matching works](https://5xer.com/docs/concepts/how-matching-works)** ·
**[API reference](https://5xer.com/docs/api)** ·
**[Migrating from TaskRouter](https://5xer.com/docs/migrate/from-twilio-taskrouter)**

> **Status:** 0.x beta. The `/v1` API is pre-stable; breaking changes are possible under `0.x`
> until `1.0` is cut alongside an API stability guarantee.

## Languages

| Language | Package | Install |
|---|---|---|
| Python | [`fivexer`](https://pypi.org/project/fivexer/) | `pip install fivexer` |
| Java / JVM / Android | `com.fivexer:fivexer-sdk` (Maven Central) | see [`java/README.md`](java/README.md) |
| PHP | `fivexer/sdk` (Packagist) | `composer require fivexer/sdk` |
| TypeScript | `@fivexer/sdk` (npm) | maintained in the platform repo |

All four cover the same surface, and two gates hold them there.
[`contract/operations.yaml`](contract/operations.yaml) is the operation checklist — each SDK has
a contract-parity test that fails the build if an operation in that catalogue has no method
behind it. [`scripts/check-field-parity.py`](scripts/check-field-parity.py) is the finer one: it
walks the reference TypeScript SDK's types and fails if a *field* it knows is missing from
Python, Java or PHP. The second exists because the first cannot see inside a request body —
`tasks.create` was present in all four SDKs while three of them silently dropped seven fields of
its input, with every parity test green.

## Three credential planes

The API has three kinds of caller, and each SDK models them as separate clients so the type
system enforces the boundary — there is no way to reach another plane's routes by accident:

| Plane | Credential | Client | What it can do |
|---|---|---|---|
| **Workspace** | `sk_test_` / `sk_live_` API key | `Fivexer` | Everything: create tasks, manage workers, teams and skills, run workflows, read stats and decisions. Server-to-server only. |
| **Worker portal** | `wt_…` session token | `FivexerWorker` | One worker's own queue: read it, accept/reject/complete, take breaks, set their own skills and shift. Cannot reach task creation or worker management. |
| **Supervisor** | `sv_…` session token | `FivexerSupervisor` | A crew lead who watches and unblocks: unpark, reprioritise, hand a task to a crew member, pause one. Scoped to one team or the whole workspace. Cannot create work or manage the roster. |

The two session planes differ in how they end. A worker token **refreshes**; a supervisor
session is redeemed from a single-use link and, once expired, can only be replaced by a new
link — so `FivexerSupervisor` has no `refresh`, and its `expiresAt` is epoch-milliseconds where
the worker plane's is an ISO-8601 string. Both are the server's shape, mirrored rather than
normalised.

## Quickstart (Python)

```python
from fivexer import Fivexer, CreateTask, UpsertWorker

client = Fivexer(base_url="https://api.5xer.com", api_key="sk_test_...")

client.workers.upsert(UpsertWorker(id="agent_1", tags=["english", "billing"]))
task = client.tasks.create(CreateTask(tags=["english", "billing"], priority=90))
# -> Task(id="task_8fk2", status="queued")

client.tasks.list()                    # paginated
client.workers.queue("agent_1")
client.decisions.list()                # who matched, and why
```

Async is identical — use `AsyncFivexer` and `await` each call.

## Quickstart (Java)

```java
Fivexer client = new Fivexer("https://api.5xer.com", "sk_test_...");
client.workers().upsert(new UpsertWorker("agent_1").tags(List.of("english", "billing")));
Task task = client.tasks().create(new CreateTask(List.of("english", "billing")).priority(90));
```

## Quickstart (PHP)

```php
$client = new Fivexer('https://api.5xer.com', 'sk_test_...');
$client->workers()->upsert((new UpsertWorker())->id('agent_1')->tags(['english', 'billing']));
$task = $client->tasks()->create((new CreateTask(['english', 'billing']))->priority(90));
```

## Quickstart (TypeScript)

```ts
const client = new Fivexer({ baseUrl: 'https://api.5xer.com', apiKey: 'sk_test_...' });
await client.workers.upsert({ id: 'agent_1', tags: ['english', 'billing'] });
const task = await client.tasks.create({ tags: ['english', 'billing'], priority: 90 });
```

## The worker portal plane

A worker logs in with workspace id + worker id + PIN, then works their own queue. `login()`
adopts the returned token and the worker id, so later calls need no extra wiring:

```python
from fivexer import FivexerWorker, WorkerLogin

worker = FivexerWorker(base_url="https://api.5xer.com")
worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

queue = worker.queue()
worker.accept(queue.task_ids[0])
worker.start_break("lunch")
worker.end_break()
```

## Surface

Every SDK exposes these groups. Names are shown Python-style; Java and PHP use the same names in
their own casing (`tasks().suggestWorkers(...)`, `$client->tasks()->suggestWorkers(...)`).

| Group | Operations |
|---|---|
| `tasks` | `create` `create_many` `check` `get` `list` `cancel` `accept` `ack` `reject` `complete` `assign` `escalate` `parked` `scheduled` `unpark` `set_priority` `suggest_workers` |
| `tasks.context` | `get` `set` `clear` |
| `tasks.comments` | `add` `list` `remove` |
| `tasks.attachments` | `create` `confirm` `list` `download` `remove` `upload` |
| `tasks.recurring` | `list` `remove` |
| `workers` | `upsert` `list` `get` `patch` `set_availability` `queue` `metrics` `time_entries` `remove` |
| `skills` | `create` `list` `get` `patch` `remove` `suggest` |
| `teams` | `create` `list` `get` `patch` `remove` `members` `set_members` |
| `join_links` | `create` `list` `revoke` |
| `identities` | `list` `invite` `resend_invite` `create` `update` `remove` |
| `decisions` | `list` |
| `workflows` | `list` `get` `save` `remove` `run` `list_runs` |
| `runs` | `list` `get` `steps` `cancel` `complete_step` `fail_step` |
| `learning` | `status` `worker_stats` `preview_weights` `apply_weights` `revert_weights` `feedback` `reward` `feedback_bulk` `reset` |
| `notifications.sequences` | `list` `create` `get` `update` `remove` |
| `notifications.channels` | `list` `create` `get` `update` `remove` |
| `stats` / `history` | `stats` `sla_stats` `queue_audit` `portal` `history.timeseries` `history.workers` `history.worker_timeseries` |
| `team` / `breaks` | `team.presence` `breaks.metrics` |
| **worker portal** | `login` `logout` `refresh` `join` `accept_invite` `me` `set_availability` `change_pin` `skill_catalog` `set_skills` `queue` `task_detail` `accept` `reject` `complete` `comments` `add_comment` `start_break` `end_break` `breaks_today` `metrics_today` `metrics_window` `time_entries` `team_presence` `update_location` `register_device` `unregister_device` `push_config` `push_subscribe` `push_unsubscribe` `attachments.create` `attachments.confirm` `attachments.list` `attachments.download` `attachments.upload` `voice_ice`¹ |

`history`, `team` and `breaks` need the control plane; a data-plane-only deployment answers
`501 history_unavailable`.

| **supervisor plane** | `entry` `accept_invite` `logout` `me` `overview` `unpark` `set_priority` `assign` `set_availability` `push_config` `push_subscribe` `push_unsubscribe` `voice_ice`¹ |

¹ **`voice_ice` is experimental.** Voice is not production-ready: the surface may change or be
withdrawn in a patch release, and no deployment should depend on it yet. It answers 404
`voice_disabled` where a workspace has voice switched off — a configuration fact, not an empty
relay list to place a call through.

All four SDKs cover the whole table — `contract/operations.yaml` is the shared checklist, and each
SDK's `ContractParityTest` walks it. Run `scripts/sync-contract.py --gaps-only` for the current
tally; at the last sync that was **150 of 152** `/v1` operations, the remaining two being HTML
pages a browser opens (the worker invite and QR-join screens), not SDK surface. Run
`scripts/check-field-parity.py` for the field-level tally — it needs a platform checkout, and
says so rather than passing when it cannot see one.

### Pausing a worker

`workers.set_availability()` covers both shapes of "not right now". A plain pause preserves the
worker's unaccepted backlog ("back in ten minutes"); `release_backlog` additionally requeues it
so others inherit it immediately ("gone for the day"). Accepted work in progress is never
touched, and the response names the tasks that moved:

```python
client.workers.set_availability("agent_1", False)                        # keeps their backlog
result = client.workers.set_availability("agent_1", False, release_backlog=True)
result.released_task_ids   # ["task_8fk2", "task_9aa3"]
```

### Attachment uploads

`tasks.attachments.upload()` performs the whole three-step dance for you — reserve a record, PUT
the bytes straight to object storage with the presigned headers, then confirm:

```python
client.tasks.attachments.upload(
    "task_8fk2", pdf_bytes, filename="receipt.pdf", content_type="application/pdf"
)
```

If storage rejects the PUT the SDK raises `upload_failed` and skips confirmation, so the record
never claims bytes that did not land.

## Webhook verification

Every SDK ships a pure helper to verify the `x-fivexer-signature` header (HMAC-SHA256, ±5 min
replay window) so you never have to hand-roll it:

```python
from fivexer import Webhook

event = Webhook.construct_event(
    payload=raw_body_bytes,
    header=request.headers["x-fivexer-signature"],
    secret="whsec_...",
)
```

See the per-language READMEs for idiomatic webhook samples.

## Repository layout

```
contract/      OpenAPI spec (synced from the platform repo), the operation catalogue every SDK
               is held to, and declarative request/response cases for each operation
python/        PyPI package `fivexer`
java/          Maven artifact `com.fivexer:fivexer-sdk`
php/           Packagist package `fivexer/sdk`
composer.json  the PHP package's manifest — at the root, not in php/
```

The stray one is `composer.json`. Packagist only ever reads a repository's **root**
`composer.json`; it has no notion of a package living in a subdirectory, so a manifest at
`php/composer.json` is a package that cannot be published. It sits at the root and points its
PSR-4 map at `php/src/`, with `config.vendor-dir` set to `php/vendor` so every path inside
`php/` — `phpunit.xml`, `tests/bootstrap.php`, `bin/check-coverage.php` — is unaffected by where
the manifest lives. Only `composer install` and `composer validate` run from the root.

The same constraint decides the tag scheme. Composer rejects any tag it cannot normalise to a
version, so `php-v0.1.0` would be skipped as an invalid version and the release would silently
not exist: **PHP releases use unprefixed `v0.1.0` tags.** The upside of the same rule is that
`python-v*` and `java-v*` tags are invisible to Packagist and cannot appear as versions of
`fivexer/sdk`.

Each SDK is hand-written rather than generated, and held to the `/v1` contract by two checks:
[`contract/operations.yaml`](contract/operations.yaml), the shared operation checklist every
SDK's `ContractParityTest` walks, and
[`scripts/check-field-parity.py`](scripts/check-field-parity.py), which holds every SDK to the
*fields* the reference TypeScript SDK knows. `scripts/sync-contract.py` refreshes the spec
snapshot and reports anything a language has not yet covered.

## Development

Each SDK gates on **90% line and branch coverage**, allows no skipped tests, and holds every test
to a 200 ms budget.

```bash
# Python — the gate lives in pyproject.toml's pytest addopts, so a bare `pytest` enforces it
cd python && pip install -e ".[dev]" && pytest
ruff check src tests && mypy src/fivexer

# Java — jacoco's 90% line + branch rule is wired into `check`
cd java && ./gradlew check

# PHP — no local PHP needed
docker build -f php/Dockerfile.dev -t fivexer-php-dev php
# Mount the repo root: composer.json lives there, and the parity test reads contract/operations.yaml
run() { docker run --rm -v "$PWD":/repo -w /repo fivexer-php-dev "$@"; }
run composer install          # installs to php/vendor, per config.vendor-dir
run sh -c 'cd php && vendor/bin/phpunit --no-coverage --log-junit build/junit.xml && php bin/check-timings.php'
run sh -c 'cd php && vendor/bin/phpunit && php bin/check-coverage.php'
```

PHP runs its tests twice on purpose: Xdebug's branch collection adds a fixed per-test overhead
that measures the profiler rather than the test, so the time budget is checked on an
uninstrumented run and the coverage gate gets its own.

## Releasing

Each language releases independently, from its own tag. Nothing here releases everything at once
— there is no repo-wide version.

| Package | Version lives in | Tag | Publishes by |
|---|---|---|---|
| `fivexer` (PyPI) | `python/pyproject.toml` → `version` | `python-v0.4.1` | OIDC trusted publishing — no token anywhere |
| `com.fivexer:fivexer-sdk` | `java/build.gradle` → `version` | `java-v0.3.0` | Signed bundle POSTed to the Central Portal Publisher API |
| `fivexer/sdk` (Packagist) | *nowhere* — the tag **is** the version | `v0.1.0` — **no prefix** | Packagist reads this repo's tags |
| `@fivexer/sdk` (npm) | `packages/sdk/package.json` | — | Released from the platform repo, not here |

**PHP's tag has no language prefix, and that is forced, not a style choice.** Composer rejects any
tag it cannot normalise to a version, so `php-v0.1.0` is skipped as invalid and the release
silently does not exist. PHP therefore owns the bare `vX.Y.Z` namespace. The same rule is what
keeps `python-v*` and `java-v*` tags invisible to Packagist so they cannot appear as versions of
`fivexer/sdk`.

### The order matters

The tag must point at a commit that already contains the version bump. A tag created before the
commit lands checks out the *old* tree, and CI will faithfully build and publish the wrong thing.

```bash
# 1. bump the version in the file above (skip for PHP — the tag is the version)
# 2. commit and push FIRST
git add -A && git commit -m "chore: release python 0.4.1"
git push origin main

# 3. wait for the language's workflow to go green on main.
#    Every release job declares `needs: test`; if the matrix fails the tag does nothing.
gh run watch --repo fivexer/sdks

# 4. only now tag, and push the tag
git tag python-v0.4.1
git push origin python-v0.4.1
```

### Guards that will stop you

- **Java** cross-checks the tag against `build.gradle`'s `version` and fails on a mismatch.
- **PHP** cross-checks the tag against `extra.branch-alias.dev-main` in `composer.json`, which
  must read `<major>.<minor>.x-dev` for the version being tagged. Bump it in the same commit.
- **Java** refuses to upload a bundle with no `.asc` signatures, so a missing `SIGNING_KEY`
  fails locally rather than as an opaque rejection from the API.

### Releases are permanent

PyPI and Maven Central are immutable: `fivexer 0.4.0` and `com.fivexer:fivexer-sdk:0.3.0` can
never be replaced, and a yanked PyPI version's number can never be reused. If a release is
wrong, the fix is always to ship the next patch version — never to re-tag.

Re-tagging is only safe **before** anything reaches a registry:

```bash
git tag -d java-v0.3.0
git push origin --delete java-v0.3.0
# fix, commit, push, wait for green, then tag again
```

If a release job fails on **credentials**, do not re-tag — secrets are read at run time and the
checkout is unchanged, so fixing the secret and re-running the job is enough:

```bash
gh run rerun --repo fivexer/sdks --failed
```

Note that a re-run uses the workflow file **as it existed at the tag**, so a fix to the workflow
itself does need a new commit and a new tag.

### Release secrets

Held as environment secrets on `maven-central`; only Java needs them, and Python needs none.

| Secret | What it is |
|---|---|
| `SIGNING_KEY` | Armored PGP **private** key (`gpg --armor --export-secret-keys`) |
| `SIGNING_PASSWORD` | The passphrase protecting that key |
| `CENTRAL_PORTAL_USERNAME` | Token **username** from Central Portal → Account → Generate User Token |
| `CENTRAL_PORTAL_TOKEN` | Token **password** from the same dialog — not your account password |

The public half of the signing key must be published to a keyserver (`keyserver.ubuntu.com`),
or Central cannot verify the signatures it just received.

## License

MIT.
