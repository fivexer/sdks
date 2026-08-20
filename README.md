# Fivexer SDKs

Hand-written, MIT-licensed HTTP-client SDKs for the **Fivexer Platform** `/v1` routing API.
Create workers and tasks; the platform matches them continuously and notifies you via webhooks.

> **Status:** 0.x beta. The `/v1` API is pre-stable; breaking changes are possible under `0.x`
> until `1.0` is cut alongside an API stability guarantee.

## Languages

| Language | Package | Install |
|---|---|---|
| Python | [`fivexer`](https://pypi.org/project/fivexer/) | `pip install fivexer` |
| Java / JVM / Android | `io.fivexer:fivexer-sdk` (Maven Central) | see [`java/README.md`](java/README.md) |
| PHP | `fivexer/sdk` (Packagist) | `composer require fivexer/sdk` |
| TypeScript | `@fivexer/sdk` (npm) | maintained in the platform repo |

All four cover the same surface. [`contract/operations.yaml`](contract/operations.yaml) is the
checklist they are held to, and each SDK has a contract-parity test that fails the build if an
operation in that catalogue has no method behind it.

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

client = Fivexer(base_url="https://api.fivexer.com", api_key="sk_test_...")

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
Fivexer client = new Fivexer("https://api.fivexer.com", "sk_test_...");
client.workers().upsert(new UpsertWorker("agent_1").tags(List.of("english", "billing")));
Task task = client.tasks().create(new CreateTask(List.of("english", "billing")).priority(90));
```

## Quickstart (PHP)

```php
$client = new Fivexer('https://api.fivexer.com', 'sk_test_...');
$client->workers()->upsert((new UpsertWorker())->id('agent_1')->tags(['english', 'billing']));
$task = $client->tasks()->create((new CreateTask(['english', 'billing']))->priority(90));
```

## Quickstart (TypeScript)

```ts
const client = new Fivexer({ baseUrl: 'https://api.fivexer.com', apiKey: 'sk_test_...' });
await client.workers.upsert({ id: 'agent_1', tags: ['english', 'billing'] });
const task = await client.tasks.create({ tags: ['english', 'billing'], priority: 90 });
```

## The worker portal plane

A worker logs in with workspace id + worker id + PIN, then works their own queue. `login()`
adopts the returned token and the worker id, so later calls need no extra wiring:

```python
from fivexer import FivexerWorker, WorkerLogin

worker = FivexerWorker(base_url="https://api.fivexer.com")
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
| `workers` | `upsert` `list` `get` `patch` `set_availability` `queue` `remove` |
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
| `stats` / `history` | `stats` `sla_stats` `queue_audit` `portal` `history.timeseries` `history.workers` |
| `team` / `breaks` | `team.presence` `breaks.metrics` |
| **worker portal** | `login` `logout` `refresh` `join` `accept_invite` `me` `set_availability` `change_pin` `skill_catalog` `set_skills` `queue` `task_detail` `accept` `reject` `complete` `comments` `add_comment` `start_break` `end_break` `breaks_today` `metrics_today` `metrics_window` `team_presence` `update_location` `register_device` `unregister_device` `push_config` `push_subscribe` `push_unsubscribe` |

`history`, `team` and `breaks` need the control plane; a data-plane-only deployment answers
`501 history_unavailable`.

| **supervisor plane** | `entry` `accept_invite` `logout` `me` `overview` `unpark` `set_priority` `assign` `set_availability` `push_config` `push_subscribe` `push_unsubscribe` |

All four SDKs cover the whole table — `contract/operations.yaml` is the shared checklist, and each
SDK's `ContractParityTest` walks it. Run `scripts/sync-contract.py --gaps-only` for the current
tally; at the last sync that was **138 of 140** `/v1` operations, the remaining two being HTML
pages a browser opens (the worker invite and QR-join screens), not SDK surface.

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
contract/   OpenAPI spec (synced from the platform repo), the operation catalogue every SDK is
            held to, and declarative request/response cases for each operation
python/     PyPI package `fivexer`
java/       Maven artifact `io.fivexer:fivexer-sdk`
php/        Packagist package `fivexer/sdk`
```

Each SDK is hand-written (not generated) and kept in sync with the `/v1` contract via the shared
[`contract/`](contract) suite. See [`PLAN.md`](PLAN.md) for the full design.

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
# Mount the repo root: the contract-parity test reads contract/operations.yaml
run() { docker run --rm -v "$PWD":/repo -w /repo/php fivexer-php-dev "$@"; }
run composer install
run sh -c 'vendor/bin/phpunit --no-coverage --log-junit build/junit.xml && php bin/check-timings.php'
run sh -c 'vendor/bin/phpunit && php bin/check-coverage.php'
```

PHP runs its tests twice on purpose: Xdebug's branch collection adds a fixed per-test overhead
that measures the profiler rather than the test, so the time budget is checked on an
uninstrumented run and the coverage gate gets its own.

## License

MIT.
