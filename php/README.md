# fivexer/sdk (PHP)

Typed PHP client for the **Fivexer Platform** `/v1` routing API. Create workers and tasks;
the platform matches them continuously and notifies you via signed webhooks.

- **Requires PHP 8.1+**
- **Guzzle 7** — the only HTTP dependency
- **Two credential planes** — `Fivexer` (workspace `sk_` key) and `FivexerWorker` (worker `wt_` token)
- **Resilient** — automatic retry on `429`/`5xx` honoring `Retry-After`, idempotency keys on creates
- **Observable** — every response updates `$client->getQuota()` from `X-Quota-*` headers

Response models expose readonly properties (`$task->id`), while input models are fluent builders
(`(new CreateTask([...]))->priority(90)`).

## Install

```bash
composer require fivexer/sdk
```

## Quickstart

```php
<?php
require 'vendor/autoload.php';

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\UpsertWorker;

$client = new Fivexer('https://api.fivexer.com', 'sk_test_...');

// Register a worker
$client->workers()->upsert((new UpsertWorker())->id('agent_1')->tags(['english', 'billing']));

// Create a task — it is queued for matching
$task = $client->tasks()->create((new CreateTask(['english', 'billing']))->priority(90));
echo $task->id . ' ' . $task->status; // task_8fk2 queued

// Inspect who got it, and why
$queue = $client->workers()->queue('agent_1');
$decisions = $client->decisions()->list($task->id);
$stats = $client->stats();
```

## Resource groups

| Accessor | Operations |
|---|---|
| `$client->tasks()` | `create` `createMany` `check` `get` `list` `cancel` `accept` `ack` `reject` `complete` `assign` `escalate` `parked` `scheduled` `unpark` `setPriority` `suggestWorkers` |
| `$client->tasks()->context()` | `get` `set` `clear` |
| `$client->tasks()->comments()` | `add` `list` `remove` |
| `$client->tasks()->attachments()` | `create` `confirm` `list` `download` `remove` `upload` |
| `$client->workers()` | `upsert` `list` `get` `patch` `setAvailability` `queue` `remove` |
| `$client->skills()` | `create` `list` `get` `patch` `remove` `suggest` |
| `$client->teams()` | `create` `list` `get` `patch` `remove` `members` `setMembers` |
| `$client->joinLinks()` | `create` `list` `revoke` |
| `$client->identities()` | `list` `invite` `resendInvite` `create` `update` `remove` |
| `$client->decisions()` | `list` |
| `$client->workflows()` | `list` `get` `save` `remove` `run` `listRuns` |
| `$client->runs()` | `list` `get` `steps` `cancel` `completeStep` `failStep` |
| `$client->learning()` | `status` `workerStats` `previewWeights` `applyWeights` `revertWeights` `feedback` `reward` `feedbackBulk` `reset` |
| `$client->notifications()->sequences()` | `list` `create` `get` `update` `remove` |
| `$client->notifications()->channels()` | `list` `create` `get` `update` `remove` |
| `$client->stats()` / `$client->history()` | `stats()` `slaStats()` `queueAudit()` `portal()` `timeseries` `workers` |
| `$client->team()` / `$client->breaks()` | `presence` `metrics` |

`history()`, `team()` and `breaks()` need the control plane; a data-plane-only deployment throws
`FivexerApiException` with `apiCode` `history_unavailable`.

## Operator actions

```php
$result = $client->tasks()->assign('task_8fk2', 'agent_2');
$result->previousWorkerId;   // 'agent_1' when it was taken from someone

// "Back in ten minutes" — keeps their unaccepted backlog
$client->workers()->setAvailability('agent_1', false);

// "Gone for the day" — requeues the backlog so others inherit it now
$released = $client->workers()->setAvailability('agent_1', false, true);
$released->releasedTaskIds;  // ['task_8fk2', 'task_9aa3']
```

The third argument to `assign($taskId, $workerId, $force)` bypasses the paused/backlog/veto/
prior-rejection checks (worker existence is still enforced).

## Rich task data

```php
use Fivexer\SDK\Model\AddComment;
use Fivexer\SDK\Model\TaskReferenceInput;

$client->tasks()->create(
    (new CreateTask(['billing']))
        ->title('Refund request')
        ->context(['orderId' => '41'])
        ->references([(new TaskReferenceInput('https://crm.example/o/41'))->label('Order 41')])
);

$client->tasks()->comments()->add(
    'task_8fk2',
    (new AddComment('Called the customer back'))->workerId('agent_1')
);

// Reserve -> PUT the bytes to object storage -> confirm, in one call
$client->tasks()->attachments()->upload('task_8fk2', $pdfBytes, 'receipt.pdf', 'application/pdf');
```

## Worker portal plane

A worker works their own queue with a `wt_` session token. `login()` adopts both the token and
the worker id, so later calls need no extra wiring:

```php
use Fivexer\SDK\FivexerWorker;
use Fivexer\SDK\Model\WorkerLogin;

$worker = new FivexerWorker('https://api.fivexer.com');
$worker->login(new WorkerLogin('ws_1', 'agent_1', '4821'));

$queue = $worker->queue();
$detail = $worker->taskDetail($queue->taskIds[0]);
$worker->accept($detail->id);
$worker->complete($detail->id, ['refunded' => true]);

$worker->startBreak('lunch');
$worker->endBreak();      // null when no break was open — a normal outcome, not an error
$worker->metricsToday();
$worker->teamPresence();
```

A worker can also arrive without a password — through a QR join link or an emailed invite. Both
mint a session, and the client adopts it, so the next call is already authenticated:

```php
use Fivexer\SDK\Model\AcceptWorkerInvite;
use Fivexer\SDK\Model\JoinWorkspace;

// QR self-registration. The worker id is generated server-side; show it to them, it is the
// username they type at the PIN screen next time.
$joined = $worker->join(new JoinWorkspace($qrToken, 'Ada', '4821'));
$joined->pendingApproval;   // true -> no work routes until an operator admits them

// Emailed invite: setting the PIN *is* the sign-in. Single-use — replaying it is a 400.
$worker->acceptInvite(new AcceptWorkerInvite($inviteToken, '4821'));

$worker->refresh();         // rotate in place; false means re-authenticate, not an error
```

Their own shift, skills and notifications:

```php
use Fivexer\SDK\Model\ChangePin;
use Fivexer\SDK\Model\PushSubscriptionInput;
use Fivexer\SDK\Model\WorkerSkillLevel;

$worker->me();                  // on shift? do skills still need setting?
$worker->setAvailability(true); // workers start off shift — this is what matches them
$worker->setSkills([new WorkerSkillLevel('sk_1', 4)]);
$worker->changePin(new ChangePin('4821', '9137'));
$worker->metricsWindow('30d');

// Push comes in two flavours. registerDevice() carries an Expo token for the native app;
// pushSubscribe() is Web Push for the browser portal. Read pushConfig() first — `enabled:
// false` means this deployment has no VAPID keypair, and a browser gives you one prompt.
if ($worker->pushConfig()->enabled) {
    $worker->pushSubscribe(new PushSubscriptionInput($endpoint, $p256dh, $auth));
}
```

The token is scoped to exactly one worker and cannot reach task creation or worker management —
calling an action before `login()` throws `worker_id_required` locally rather than guessing an id.

## Operator onboarding

Getting workers into a workspace, from the `sk_` side:

```php
use Fivexer\SDK\Model\CreateJoinLink;
use Fivexer\SDK\Model\InviteWorkerIdentity;
use Fivexer\SDK\Model\UpsertTeam;

$client->teams()->create(new UpsertTeam('billing', 'Billing'));  // `tag` is derived, and routes

$invite = $client->identities()->invite(
    (new InviteWorkerIdentity('ada@example.com'))->label('Ada')
);
$invite->emailStatus;   // 'mailer_unconfigured' is common — then inviteUrl is the only path
$invite->inviteUrl;     // credential-equivalent until consumed; treat it as a secret

$link = $client->joinLinks()->create((new CreateJoinLink('Warehouse'))->maxUses(25));
$link->joinUrl;         // returned only here — a lost link is re-created, never recovered
```

## Error handling

Non-2xx responses throw `FivexerApiException`. The API's error code is on `$e->apiCode` — the
inherited `getCode()` is PHP's numeric exception code and is always `0` here. On `402`/`429` the
quota snapshot and retry-after are attached:

```php
use Fivexer\SDK\Exception\FivexerApiException;

try {
    $client->tasks()->create(new CreateTask(['english']));
} catch (FivexerApiException $e) {
    echo $e->statusCode . ' ' . $e->apiCode;   // 429 rate_limited
    echo $e->retryAfterSeconds;                 // 60.0
    echo $e->quota->taskRateRemaining;          // 0
}
```

## Webhooks

Verify the `x-fivexer-signature` header (HMAC-SHA256, ±5 min replay window) with the pure
`Webhook` helper — pass the **raw** request body:

```php
use Fivexer\SDK\Webhook\Webhook;

$event = Webhook::constructEvent(
    $requestBodyBytes,                         // raw bytes
    $_SERVER['HTTP_X_FIVEXER_SIGNATURE'],
    'whsec_...',
);
echo $event->getEvent(); // task.matched
```

## Development

No local PHP needed — `Dockerfile.dev` provides PHP 8.3 with Xdebug (branch coverage) and
Composer:

```bash
docker build -f Dockerfile.dev -t fivexer-php-dev .

# Mount the repo root, not just php/ — the contract-parity test reads ../contract/
run() { docker run --rm -v "$PWD/..":/repo -w /repo/php fivexer-php-dev "$@"; }

run composer install

# Tests + the 200ms per-test budget
run sh -c 'vendor/bin/phpunit --no-coverage --log-junit build/junit.xml && php bin/check-timings.php'

# Coverage gate (90% line + branch)
run sh -c 'vendor/bin/phpunit && php bin/check-coverage.php'
```

The two runs are deliberate: Xdebug's branch collection adds a fixed per-test overhead that
measures the profiler rather than the test, so the time budget is checked on an uninstrumented
run and the coverage gate gets its own.

## License

MIT.
