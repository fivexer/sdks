# fivexer-sdk (Java / JVM / Android)

Typed Java client for the **Fivexer Platform** `/v1` routing API. Create workers and tasks; the
platform matches them continuously and notifies you via signed webhooks. Works on the JVM
(server) and Android (Java 8+ bytecode).

- **OkHttp 4 + Gson** — lightweight, Android-friendly
- **Two credential planes** — `Fivexer` (workspace `sk_` key) and `FivexerWorker` (worker `wt_` token)
- **Sync + async-ready** — `Fivexer` (blocking); async via `CompletableFuture` wrappers
- **Resilient** — automatic retry on `429`/`5xx` honoring `Retry-After`, idempotency keys on creates
- **Observable** — every response updates `client.getQuota()` from `X-Quota-*` headers

## Install

### Maven

```xml
<dependency>
  <groupId>com.fivexer</groupId>
  <artifactId>fivexer-sdk</artifactId>
  <version>0.3.0</version>
</dependency>
```

### Gradle

```groovy
implementation 'com.fivexer:fivexer-sdk:0.3.0'
```

> The `com.fivexer` namespace must be verified on Maven Central Portal — by a DNS TXT record on
> `fivexer.com` — before the first release. The groupId is a claim about a domain you control,
> which is why it is `com.fivexer` and not `io.fivexer`: the latter would require `fivexer.io`.
>
> The Java *package* is `io.fivexer.sdk` and stays that way; Central verifies the coordinate,
> not the package, so imports are unaffected by the groupId.

## Quickstart

```java
import io.fivexer.sdk.Fivexer;
import io.fivexer.sdk.model.*;

Fivexer client = new Fivexer("https://api.5xer.com", "sk_test_...");

// Register a worker
client.workers().upsert(new UpsertWorker("agent_1").tags(List.of("english", "billing")));

// Create a task — it is queued for matching
Task task = client.tasks().create(new CreateTask(List.of("english", "billing")).priority(90));
System.out.println(task.getId() + " " + task.getStatus()); // task_8fk2 queued

// Inspect who got it, and why
WorkerQueue queue = client.workers().queue("agent_1");
List<Decision> decisions = client.decisions().list(new ListDecisionsQuery().taskId(task.getId()));
WorkspaceStats stats = client.stats();
```

## Resource groups

| Accessor | Operations |
|---|---|
| `client.tasks()` | `create` `createMany` `check` `get` `list` `cancel` `accept` `ack` `reject` `complete` `assign` `escalate` `parked` `scheduled` `unpark` `setPriority` `suggestWorkers` |
| `client.tasks().context()` | `get` `set` `clear` |
| `client.tasks().comments()` | `add` `list` `remove` |
| `client.tasks().attachments()` | `create` `confirm` `list` `download` `remove` `upload` |
| `client.workers()` | `upsert` `list` `get` `patch` `setAvailability` `queue` `metrics` `timeEntries` `remove` |
| `client.skills()` | `create` `list` `get` `patch` `remove` `suggest` |
| `client.teams()` | `create` `list` `get` `patch` `remove` `members` `setMembers` |
| `client.joinLinks()` | `create` `list` `revoke` |
| `client.identities()` | `list` `invite` `resendInvite` `create` `update` `remove` |
| `client.decisions()` | `list` |
| `client.workflows()` | `list` `get` `save` `remove` `run` `listRuns` |
| `client.runs()` | `list` `get` `steps` `cancel` `completeStep` `failStep` |
| `client.learning()` | `status` `workerStats` `previewWeights` `applyWeights` `revertWeights` `feedback` `reward` `feedbackBulk` `reset` |
| `client.notifications().sequences()` | `list` `create` `get` `update` `remove` |
| `client.notifications().channels()` | `list` `create` `get` `update` `remove` |
| `client.stats()` / `client.history()` | `stats()` `slaStats()` `queueAudit()` `portal()` `timeseries` `workers` `workerTimeseries` |
| `client.team()` / `client.breaks()` | `presence` `metrics` |

`history()`, `team()` and `breaks()` need the control plane; a data-plane-only deployment throws
`FivexerApiException` with code `history_unavailable`.

## Recorded time

A shift is a recorded stretch of availability; breaks are time taken inside one, so worked time
is shift time minus breaks. These are measured facts, not a rating, and nothing here feeds back
into matching.

```java
WorkerMetrics metrics = client.workers().metrics("agent_1", "7d");
metrics.getToday().getOnShiftMs();      // 8h on shift
metrics.getToday().getWorkingMs();      // less the breaks inside it
metrics.getWindow().getAcceptanceRate();// null before any offer, never 0.0

// The exportable working-time record (CJEU C-55/18), shifts and breaks in order
WorkerTimeEntriesResult log = client.workers().timeEntries("agent_1");
log.getEntries().get(0).getEndReason(); // "timeout" -> the platform clocked out a silent
                                        // unattended worker; null while the shift is open
log.getTotals().getWorkingMs();

// One worker's series. Worked time and the lifecycle counters are day-grained, so they are
// null on hour buckets rather than zero.
client.history().workerTimeseries("agent_1", new StatsWindowQuery().bucket("day"));

// Per-worker productivity, optionally narrowed to one crew
client.history().workers(new WorkerStatsQuery().teamId("team_night"));
```

## Operator actions

```java
AssignTaskResult result = client.tasks().assign("task_8fk2", "agent_2");
result.getPreviousWorkerId();   // "agent_1" when it was taken from someone

// "Back in ten minutes" — keeps their unaccepted backlog
client.workers().setAvailability("agent_1", false);

// "Gone for the day" — requeues the backlog so others inherit it now
WorkerAvailability released = client.workers().setAvailability("agent_1", false, true);
released.getReleasedTaskIds();  // [task_8fk2, task_9aa3]
```

The three-argument `assign(taskId, workerId, force)` bypasses the paused/backlog/veto/
prior-rejection checks (worker existence is still enforced).

## Rich task data

```java
client.tasks().create(new CreateTask(List.of("billing"))
        .title("Refund request")
        .context(Map.of("orderId", "41"))
        .references(List.of(new TaskReference("https://crm.example/o/41").label("Order 41"))));

client.tasks().comments().add("task_8fk2",
        new AddComment("Called the customer back").workerId("agent_1"));

// Reserve -> PUT the bytes to object storage -> confirm, in one call
client.tasks().attachments().upload("task_8fk2", pdfBytes, "receipt.pdf", "application/pdf");
```

## Task policies

Four policies ride on a task, each with its own clock. Omitting one inherits the workspace
default; `escalationNone()` / `slaNone()` send an explicit null to opt out of that default —
absent and null are different instructions, and Gson's omit-nulls rule cannot express the second
on its own.

```java
client.tasks().create(new CreateTask(List.of("billing"))
        // the response clock: who gets it next when nobody answers
        .escalation(new EscalationPolicy(60_000)
                .onNoResponse("block")
                .tiers(List.of(List.of("billing"), List.of("billing", "english")))
                .onExhausted("park"))
        // the completion clock, shelf life and rejection budget
        .sla(new SlaPolicy().completeWithinMs(3_600_000).maxRejections(3).onExpire("park"))
        // when it may be offered at all
        .schedule(new SchedulePolicy().notBefore(startsAt).notAfter(closesAt).onMiss("park"))
        .teamId("team_1"));            // hard gate; preferTeamId() only reorders

client.tasks().create(new CreateTask(List.of("billing")).slaNone());  // opt out of the default
```

A `recurrence` makes a standing **template** instead of a one-off. The template is never itself
matchable and never appears in `tasks().list()` or the queue stats — occurrences are cut from it
one interval ahead of their window, aligned to `startAt + k × everyMs` so they never drift:

```java
client.tasks().create(new CreateTask(List.of("ops"))
        .id("nightly-sweep")
        .recurrence(new RecurrencePolicy(86_400_000).windowMs(3_600_000).onMiss("park")));

for (RecurringTask template : client.tasks().recurring().list()) {
    System.out.println(template.getId() + " next at " + template.getNextAt());
}
client.tasks().recurring().remove("nightly-sweep");        // occurrences already cut live on
client.tasks().recurring().remove("nightly-sweep", true);  // ...unless you drop them too
```

## Worker portal plane

A worker works their own queue with a `wt_` session token. `login()` adopts both the token and
the worker id, so later calls need no extra wiring:

```java
FivexerWorker worker = new FivexerWorker("https://api.5xer.com");
worker.login(new WorkerLogin("ws_1", "agent_1", "4821"));

WorkerQueue queue = worker.queue();
WorkerTaskDetail detail = worker.taskDetail(queue.getTaskIds().get(0));
worker.accept(detail.getId());
worker.complete(detail.getId(), Map.of("refunded", true));

worker.startBreak("lunch");
worker.endBreak();          // null when no break was open — a normal outcome, not an error
worker.metricsToday();
worker.timeEntries();       // their own copy of the record an operator reads
worker.teamPresence();
```

An **unattended** worker — a daemon rather than a person — can attach a liveness contract when
going on shift, so a crashed process stops reading as available:

```java
worker.setAvailability(true, 900_000L);  // clock me out after 15 min of silence
```

Interactive clients must never send it: someone working away from their phone is not a crashed
process. It is only meaningful alongside `available: true`, and the SDK sends it only then.

A worker can also arrive without a password — through a QR join link or an emailed invite. Both
mint a session, and the client adopts it, so the next call is already authenticated:

```java
// QR self-registration. The worker id is generated server-side; show it to them, it is the
// username they type at the PIN screen next time.
JoinWorkspaceResult joined = worker.join(new JoinWorkspace(qrToken, "Ada", "4821"));
joined.getPendingApproval();   // true -> no work routes until an operator admits them

// Emailed invite: setting the PIN *is* the sign-in. Single-use — replaying it is a 400.
worker.acceptInvite(new AcceptWorkerInvite(inviteToken, "4821"));

worker.refresh();              // rotate in place; false means re-authenticate, not an error
```

Their own shift, skills and notifications:

```java
worker.me();                   // on shift? do skills still need setting?
worker.setAvailability(true);  // workers start off shift — this is what matches them
worker.setSkills(List.of(new WorkerSkillLevel("sk_1", 4)));
worker.changePin(new ChangePin("4821", "9137"));
worker.metricsWindow("30d");

// Push comes in two flavours. `registerDevice` carries an Expo token for the native app;
// `pushSubscribe` is Web Push for the browser portal. Read pushConfig() first — `enabled:
// false` means this deployment has no VAPID keypair, and a browser gives you one prompt.
if (Boolean.TRUE.equals(worker.pushConfig().getEnabled())) {
    worker.pushSubscribe(new PushSubscriptionInput(endpoint, p256dh, auth));
}
```

The token is scoped to exactly one worker and cannot reach task creation or worker management —
calling an action before `login()` throws `worker_id_required` locally rather than guessing an id.

### Files and voice on the worker plane

A worker can attach files to their own tasks — how an unattended agent hands over a deliverable
as a file rather than a chunked comment thread. Same storage core as the workspace plane, minus
two things on purpose: the uploader comes from the session (no `workerId` to spoof), and there is
no `remove`, because a worker who could delete files could erase the evidence of their own work.

```java
worker.attachments().upload("task_8fk2", pdfBytes, "report.pdf", "application/pdf");
List<Attachment> files = worker.attachments().list("task_8fk2");
```

`worker.voiceIce()` and `supervisor.voiceIce()` return the STUN/TURN servers for a call.
**Experimental — voice is not production-ready**; the surface may change or be withdrawn in a
patch release. Fetch it per call rather than caching: a TURN credential is short-lived, and a
stale one fails at the point where the call is already ringing. A workspace with voice switched
off answers 404 `voice_disabled` — a configuration fact, not an empty relay list to dial through.

## Operator onboarding

Getting workers into a workspace, from the `sk_` side:

```java
client.teams().create(new UpsertTeam("billing", "Billing"));  // `tag` is derived, and routes

WorkerInviteResult invite = client.identities().invite(
        new InviteWorkerIdentity("ada@example.com").label("Ada"));
invite.getEmailStatus();  // 'mailer_unconfigured' is common — then inviteUrl is the only path
invite.getInviteUrl();    // credential-equivalent until consumed; treat it as a secret

CreateJoinLinkResult link = client.joinLinks().create(new CreateJoinLink("Warehouse").maxUses(25));
link.getJoinUrl();        // returned only here — a lost link is re-created, never recovered
```

## Supervisor plane

A crew lead watches and unblocks work rather than doing it. A session is redeemed from a
single-use link — there is no login and no refresh, so an expired session means "get a new link":

```java
FivexerSupervisor sup = new FivexerSupervisor("https://api.5xer.com");
sup.acceptInvite(new AcceptSupervisorInvite(linkToken));

SupervisorOverview board = sup.overview();   // counts, crew and parked work in ONE request
board.getCounts().getOldestWaitMs();
board.getCrew().get(0).getWorkerId();        // the busiest crew member

sup.unpark(board.getParked().get(0).getId());
sup.assign("task_8fk2", "agent_1");
sup.setAvailability("agent_1", false, true); // pause and redistribute pending work
```

Scope is enforced server-side: a task from another crew is a 403, not a silent move.
`getSessionExpiresAt()` is epoch-milliseconds here, not the ISO string the worker plane uses.

## Error handling

Non-2xx responses throw `FivexerApiException` with the API's `code` and, on `402`/`429`, the quota
snapshot and retry-after:

```java
try {
    client.tasks().create(new CreateTask(List.of("english")));
} catch (FivexerApiException e) {
    System.out.println(e.getStatusCode() + " " + e.getCode()); // 429 rate_limited
    System.out.println(e.getRetryAfterSeconds());               // 60.0
    System.out.println(e.getQuota().getTaskRateRemaining());    // 0
}
```

## Webhooks

Verify the `x-fivexer-signature` header (HMAC-SHA256, ±5 min replay window) with the pure
`Webhook` helper — pass the **raw** request body:

```java
import io.fivexer.sdk.webhook.Webhook;
import io.fivexer.sdk.webhook.WebhookEvent;

WebhookEvent event = Webhook.constructEvent(
    requestBodyBytes,                              // raw bytes
    request.getHeader("x-fivexer-signature"),
    "whsec_..."
);
System.out.println(event.getEvent()); // task.matched
System.out.println(event.getData());  // { taskId=..., workerId=... }
```

## Build & test

```bash
./gradlew check             # build + tests + the 90% line/branch coverage gate
./gradlew test              # tests only
./gradlew jacocoTestReport  # HTML coverage report in build/reports/jacoco/
```

Per-test durations are in `build/reports/tests/test/index.html`; every test is held to a 200 ms
budget, and no test may be `@Disabled`.

## License

MIT.
