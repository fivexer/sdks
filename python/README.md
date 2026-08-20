# fivexer (Python)

Typed Python client for the **Fivexer Platform** `/v1` routing API. Create workers and tasks;
the platform matches them continuously and notifies you via signed webhooks.

- **Sync + async**: `Fivexer` and `AsyncFivexer`, with identical surfaces
- **Two credential planes**: `Fivexer` (workspace `sk_` key) and `FivexerWorker` (worker `wt_` token)
- **Zero-config deps**: only `httpx`
- **Typed**: dataclass models, full type hints, `mypy --strict` clean
- **Resilient**: automatic retry on `429`/`5xx` honoring `Retry-After`, idempotency keys on creates
- **Observable**: every response updates `client.quota` from `X-Quota-*` headers

## Install

```bash
pip install fivexer
```

## Quickstart

```python
from fivexer import Fivexer, CreateTask, UpsertWorker

client = Fivexer(base_url="https://api.5xer.com", api_key="sk_test_...")

# Register a worker
client.workers.upsert(UpsertWorker(id="agent_1", tags=["english", "billing"]))

# Create a task — it is queued for matching
task = client.tasks.create(CreateTask(tags=["english", "billing"], priority=90))
print(task.id, task.status)  # task_8fk2 queued

# Inspect who got it, and why
client.workers.queue("agent_1")
client.decisions.list(task_id=task.id)
```

Async is identical (`AsyncFivexer`), just `await` each call.

## Resource groups

| Group | Operations |
|---|---|
| `client.tasks` | `create` `create_many` `check` `get` `list` `cancel` `accept` `ack` `reject` `complete` `assign` `escalate` `parked` `scheduled` `unpark` `set_priority` `suggest_workers` |
| `client.tasks.context` | `get` `set` `clear` |
| `client.tasks.comments` | `add` `list` `remove` |
| `client.tasks.attachments` | `create` `confirm` `list` `download` `remove` `upload` |
| `client.workers` | `upsert` `list` `get` `patch` `set_availability` `queue` `remove` |
| `client.skills` | `create` `list` `get` `patch` `remove` `suggest` |
| `client.teams` | `create` `list` `get` `patch` `remove` `members` `set_members` |
| `client.join_links` | `create` `list` `revoke` |
| `client.identities` | `list` `invite` `resend_invite` `create` `update` `remove` |
| `client.decisions` | `list` |
| `client.workflows` | `list` `get` `save` `remove` `run` `list_runs` |
| `client.runs` | `list` `get` `steps` `cancel` `complete_step` `fail_step` |
| `client.learning` | `status` `worker_stats` `preview_weights` `apply_weights` `revert_weights` `feedback` `reward` `feedback_bulk` `reset` |
| `client.notifications.sequences` | `list` `create` `get` `update` `remove` |
| `client.notifications.channels` | `list` `create` `get` `update` `remove` |
| `client.stats()` / `client.history` | `stats()` `sla_stats()` `queue_audit()` `portal()` `timeseries` `workers` |
| `client.team` / `client.breaks` | `presence` `metrics` |

`history`, `team` and `breaks` need the control plane; a data-plane-only deployment raises
`FivexerApiError` with code `history_unavailable`.

## Operator actions

Hand a task to a specific worker, or take a worker off the line:

```python
result = client.tasks.assign("task_8fk2", "agent_2")
result.previous_worker_id       # "agent_1" when it was taken from someone

# "Back in ten minutes" — keeps their unaccepted backlog
client.workers.set_availability("agent_1", False)

# "Gone for the day" — requeues the backlog so others inherit it now
released = client.workers.set_availability("agent_1", False, release_backlog=True)
released.released_task_ids      # ["task_8fk2", "task_9aa3"]
```

`force=True` on `assign` bypasses the paused/backlog/veto/prior-rejection checks (worker
existence is still enforced).

## Rich task data

A task can carry a title, description, free-form context and references — inline at creation or
written separately:

```python
from fivexer import CreateTask, TaskReferenceInput, AddComment

client.tasks.create(CreateTask(
    tags=["billing"],
    title="Refund request",
    context={"orderId": "41"},
    references=[TaskReferenceInput(url="https://crm.example/o/41", label="Order 41")],
))

client.tasks.comments.add("task_8fk2", AddComment(body="Called the customer back",
                                                  worker_id="agent_1"))

# Reserve -> PUT the bytes to object storage -> confirm, in one call
client.tasks.attachments.upload("task_8fk2", pdf_bytes,
                                filename="receipt.pdf", content_type="application/pdf")
```

## Worker portal plane

A worker works their own queue with a `wt_` session token. `login()` adopts both the token and
the worker id, so later calls need no extra wiring:

```python
from fivexer import FivexerWorker, WorkerLogin

worker = FivexerWorker(base_url="https://api.5xer.com")
worker.login(WorkerLogin(workspace_id="ws_1", worker_id="agent_1", pin="4821"))

queue = worker.queue()
detail = worker.task_detail(queue.task_ids[0])
worker.accept(detail.id)
worker.complete(detail.id, {"refunded": True})

worker.start_break("lunch")
worker.end_break()              # None when no break was open — a normal outcome, not an error
worker.metrics_today()
worker.team_presence()
```

A worker can also sign in without a password at all — through a QR join link, or an emailed
invite. Both mint a session, and the client adopts it, so the next call is already authenticated:

```python
from fivexer import AcceptWorkerInvite, JoinWorkspace

worker = FivexerWorker(base_url="https://api.5xer.com")

# QR self-registration: the worker id is generated server-side — show it to them, it is the
# username they type at the PIN screen next time.
result = worker.join(JoinWorkspace(token="<from the QR>", name="Ada", pin="4821"))
result.pending_approval        # True -> no work routes until an operator admits them

# Emailed invite: setting the PIN *is* the sign-in.
worker.accept_invite(AcceptWorkerInvite(token="<from the link>", pin="4821"))

worker.refresh()               # rotate in place; False means "re-authenticate", not an error
```

And manage their own shift, skills and notifications:

```python
from fivexer import ChangePin, PushSubscriptionInput, WorkerSkillLevel

worker.me()                            # who am I, am I on shift, do skills still need setting
worker.set_availability(True)          # workers are created off shift — this is what matches them
worker.set_skills([WorkerSkillLevel(skill_id="sk_1", level=4)])   # replaces the whole set
worker.change_pin(ChangePin(current_pin="4821", new_pin="9137"))
worker.metrics_window("30d")

# Web Push. Check config first: `enabled=False` means this deployment has no VAPID keypair,
# and a browser only gives you one permission prompt.
if worker.push_config().enabled:
    worker.push_subscribe(PushSubscriptionInput(endpoint="https://fcm/...", p256dh="...", auth="..."))
```

`AsyncFivexerWorker` is the awaited mirror. The token is scoped to exactly one worker and cannot
reach task creation or worker management — calling an action before `login()` raises
`worker_id_required` locally rather than guessing an id.

## Operator onboarding

Getting workers into a workspace, from the `sk_` side:

```python
from fivexer import CreateJoinLink, InviteWorkerIdentity, UpsertTeam

client.teams.create(UpsertTeam(key="billing", name="Billing"))   # `tag` is derived, and routes

invite = client.identities.invite(InviteWorkerIdentity(email="ada@example.com", label="Ada"))
invite.email_status    # 'mailer_unconfigured' is common — then invite_url is the only delivery
invite.invite_url      # credential-equivalent until consumed; treat it as a secret

link = client.join_links.create(CreateJoinLink(label="Warehouse hires", max_uses=25))
link.join_url          # returned only here — a lost link is re-created, never recovered
```

## Supervisor plane

A crew lead watches and unblocks work rather than doing it. A session is redeemed from a
single-use link an owner generated in the console — there is no login and no refresh, so an
expired session means "get a new link":

```python
from fivexer import AcceptSupervisorInvite, FivexerSupervisor

sup = FivexerSupervisor("https://api.5xer.com")
sup.accept_invite(AcceptSupervisorInvite(token="<from the link>"))

board = sup.overview()          # counts, crew (busiest first) and parked work, in ONE request
board.counts.oldest_wait_ms
board.crew[0].worker_id         # the busiest crew member

sup.unpark(board.parked[0].id)  # back to the queue
sup.assign("task_8fk2", "agent_1")
sup.set_availability("agent_1", False, release_backlog=True)
```

`AsyncFivexerSupervisor` is the awaited mirror. Scope is enforced server-side: a task from
another crew is a 403, not a silent move. `session_expires_at` is epoch-milliseconds here, not
the ISO string the worker plane uses — the two planes genuinely differ on the wire.

## Error handling

Non-2xx responses raise `FivexerApiError` with the API's `code` and, on `402`/`429`, the quota
snapshot and `retry_after`:

```python
from fivexer import FivexerApiError

try:
    client.tasks.create(CreateTask(tags=["english"]))
except FivexerApiError as e:
    print(e.status_code, e.code)      # 429 rate_limited
    print(e.retry_after)              # 60.0
    print(e.quota.task_rate_remaining)  # 0
```

## Webhooks

Verify the `x-fivexer-signature` header (HMAC-SHA256, ±5 min replay window) with the pure
`Webhook` helper — pass the **raw** request body:

```python
from fivexer import Webhook

event = Webhook.construct_event(
    payload=request.body,                      # raw bytes
    header=request.headers["x-fivexer-signature"],
    secret="whsec_...",
)
print(event.event)   # task.matched
print(event.data)    # { taskId, workerId, ... }
```

## Development

```bash
pip install -e ".[dev]"
pytest                 # 90% line + branch gate is in pyproject.toml's addopts
ruff check src tests
mypy src/fivexer
```

## License

MIT.
