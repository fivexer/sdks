<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\AssignTaskResult;
use Fivexer\SDK\Model\BulkTaskReport;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\SlotBooking;
use Fivexer\SDK\Model\TaskCheckReport;
use Fivexer\SDK\Model\TaskEscalation;
use Fivexer\SDK\Model\TaskHistoryPage;
use Fivexer\SDK\Model\TaskHistoryQuery;
use Fivexer\SDK\Model\TaskList;
use Fivexer\SDK\Model\UnparkTask;
use Fivexer\SDK\Model\SuggestWorkers;
use Fivexer\SDK\Model\SuggestWorkersResult;
use Fivexer\SDK\Model\Task;
use Fivexer\SDK\Model\TaskAction;
use Fivexer\SDK\Model\TaskPage;
use Fivexer\SDK\Model\TaskPriority;
use Fivexer\SDK\Model\UpdateTaskInput;
use Fivexer\SDK\Model\UpdateTaskResult;

/**
 * Task lifecycle resource. Delegates to Fivexer::request() so all transport logic
 * (auth, retry, quota, errors) lives in one place.
 */
final class Tasks
{
    private const PARAM_STATUS = 'status';
    private const PARAM_CURSOR = 'cursor';
    private const PARAM_LIMIT = 'limit';

    private readonly TaskContexts $contextResource;
    private readonly TaskComments $commentsResource;
    private readonly TaskAttachments $attachmentsResource;
    private readonly TaskRecurring $recurringResource;
    private readonly TaskBooking $bookingResource;

    public function __construct(private readonly Fivexer $client)
    {
        $this->contextResource = new TaskContexts($client);
        $this->commentsResource = new TaskComments($client);
        $this->attachmentsResource = new TaskAttachments($client);
        $this->recurringResource = new TaskRecurring($client);
        $this->bookingResource = new TaskBooking($client);
    }

    /** The rich data stored against a task: title, description, context and references. */
    public function context(): TaskContexts
    {
        return $this->contextResource;
    }

    /** Comments on a task. */
    public function comments(): TaskComments
    {
        return $this->commentsResource;
    }

    /** Files attached to a task. */
    public function attachments(): TaskAttachments
    {
        return $this->attachmentsResource;
    }

    /** Standing templates that occurrences are cut from. */
    public function recurring(): TaskRecurring
    {
        return $this->recurringResource;
    }

    /** The booking on a slotted task — who is reserved for its appointment. */
    public function booking(): TaskBooking
    {
        return $this->bookingResource;
    }

    /** Create a task; the server queues it for matching. */
    public function create(CreateTask $input): Task
    {
        $data = $this->client->request('POST', '/tasks', $input->toArray()) ?? [];
        return Task::fromArray($data);
    }

    public function get(string $taskId): Task
    {
        return Task::fromArray($this->client->request('GET', '/tasks/' . \rawurlencode($taskId)) ?? []);
    }

    /**
     * @param string|null $status 'queued'|'pending'|'accepted'|'all'
     * @param string|null $cursor Pagination cursor from a previous page's nextCursor
     * @param int<1, max>|null $limit Page size
     */
    public function list(?string $status = null, ?string $cursor = null, ?int $limit = null): TaskPage
    {
        $data = $this->client->request('GET', '/tasks', null, [
            self::PARAM_STATUS => $status,
            self::PARAM_CURSOR => $cursor,
            self::PARAM_LIMIT => $limit,
        ]) ?? [];
        return TaskPage::fromArray($data);
    }

    /**
     * Finished tasks, newest first — the archive, not the live stores. Several statuses may be
     * asked for at once; they travel as one comma-joined parameter. Page with the result's
     * nextCursor. Requires the control plane.
     */
    public function history(?TaskHistoryQuery $query = null): TaskHistoryPage
    {
        return TaskHistoryPage::fromArray(
            $this->client->request('GET', '/tasks/history', null, ($query ?? new TaskHistoryQuery())->toQuery()) ?? []
        );
    }

    /**
     * Booked appointments overlapping a window — the planner's calendar. Bookings are
     * reservations of a worker's time, not tasks in a state, which is why no task listing
     * answers "who is on what, when".
     *
     * @param int $from Epoch ms, start of the window
     * @param int $to Epoch ms, end of the window
     * @param string|null $workerId One worker's calendar, or null for everyone's
     * @return list<SlotBooking>
     */
    public function bookings(int $from, int $to, ?string $workerId = null): array
    {
        $data = $this->client->request('GET', '/tasks/bookings', null, [
            'from' => $from,
            'to' => $to,
            'workerId' => $workerId,
        ]) ?? [];
        /** @var list<SlotBooking> */
        return Json::parseEach($data, 'bookings', [SlotBooking::class, 'fromArray']);
    }

    /** Cancel a queued task. */
    public function cancel(string $taskId): void
    {
        $this->client->request('DELETE', '/tasks/' . \rawurlencode($taskId));
    }

    /**
     * Create many tasks in one call.
     *
     * Partial success is normal — a 200 does not mean every task was created. Read `failed` and
     * the per-entry results, whose `index` maps back to this list.
     *
     * @param list<CreateTask> $tasks
     */
    public function createMany(array $tasks): BulkTaskReport
    {
        return BulkTaskReport::fromArray($this->client->request('POST', '/tasks/bulk', [
            'tasks' => \array_map(static fn (CreateTask $t): array => $t->toArray(), $tasks),
        ]) ?? []);
    }

    /**
     * Dry-run a task: how many workers could take it, which of its tags nobody covers, and what
     * is wrong with its policies. Creates nothing and reserves nothing.
     */
    public function check(CreateTask $input): TaskCheckReport
    {
        return TaskCheckReport::fromArray($this->client->request('POST', '/tasks/check', $input->toArray()) ?? []);
    }

    /** Acknowledge an offer without starting work — stops the response clock only. */
    public function ack(string $taskId, string $workerId): TaskAction
    {
        return TaskAction::fromArray(
            $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/ack', [
                'workerId' => $workerId,
            ]) ?? []
        );
    }

    /**
     * Advance the escalation ladder now. `parked` comes back true when the ladder was already
     * exhausted, which takes the task out of matching.
     */
    public function escalate(string $taskId, ?string $workerId = null): TaskEscalation
    {
        $body = $workerId === null ? [] : ['workerId' => $workerId];
        return TaskEscalation::fromArray(
            $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/escalate', $body) ?? []
        );
    }

    /**
     * Tasks a policy gave up on — an exhausted escalation ladder, an SLA breach handled with
     * park, or a rejection budget run dry. Out of matching, but recoverable with unpark().
     */
    public function parked(): TaskList
    {
        return TaskList::fromArray($this->client->request('GET', '/tasks/parked') ?? []);
    }

    /**
     * Tasks held by a `schedule.notBefore`, soonest activation first — the only view of work
     * that has been booked but has not started.
     */
    public function scheduled(): TaskList
    {
        return TaskList::fromArray($this->client->request('GET', '/tasks/scheduled') ?? []);
    }

    /**
     * Return a parked task to the queue. Reset the clocks that parked it, or the next sweep may
     * park it straight back.
     */
    public function unpark(string $taskId, ?UnparkTask $options = null): TaskAction
    {
        return TaskAction::fromArray(
            $this->client->request(
                'POST',
                '/tasks/' . \rawurlencode($taskId) . '/unpark',
                $options === null ? [] : $options->toArray()
            ) ?? []
        );
    }

    public function accept(string $taskId, string $workerId): TaskAction
    {
        return $this->workerAction($taskId, 'accept', $workerId);
    }

    public function reject(string $taskId, string $workerId): TaskAction
    {
        return $this->workerAction($taskId, 'reject', $workerId);
    }

    /** @param array<string, mixed>|null $result */
    public function complete(string $taskId, string $workerId, ?array $result = null): TaskAction
    {
        $body = ['workerId' => $workerId];
        if ($result !== null) {
            $body['result'] = $result;
        }
        $data = $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/complete', $body) ?? [];
        return TaskAction::fromArray($data);
    }

    /** Report that accepted work could not be done. Only its holder may; counts as a failure. */
    public function fail(string $taskId, string $workerId, ?string $reason = null): TaskAction
    {
        $body = ['workerId' => $workerId];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }
        $data = $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/fail', $body) ?? [];
        return TaskAction::fromArray($data);
    }

    /** DRY helper: accept/reject share the exact same shape (workerId only). */
    private function workerAction(string $taskId, string $action, string $workerId): TaskAction
    {
        $data = $this->client->request(
            'POST',
            '/tasks/' . \rawurlencode($taskId) . '/' . $action,
            ['workerId' => $workerId],
        ) ?? [];
        return TaskAction::fromArray($data);
    }

    /**
     * Operator override: hand a task to a specific worker. Queued tasks go through the same
     * claim gate as organic matching; a pending task is transferred and its expiry clock
     * restarts. The result names the worker it was taken from, if any.
     *
     * @param bool $force Bypass the paused/backlog/veto/prior-rejection checks (worker
     *     existence is still enforced)
     */
    public function assign(string $taskId, string $workerId, bool $force = false): AssignTaskResult
    {
        $body = ['workerId' => $workerId];
        if ($force) {
            $body['force'] = true;
        }
        return AssignTaskResult::fromArray(
            $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/assign', $body) ?? []
        );
    }

    /**
     * Edit a live task — routing tags, priority, title, description, context, references, meta.
     * Omitted fields are left alone; the input's clear…() methods send null for the clearable
     * ones.
     *
     * Changing the tags changes who is eligible. If they no longer reach the worker holding the
     * task it is taken off them and returned to the queue — check `requeued` on the result, or
     * a retag that moved work will look like a no-op. An edit that leaves the tags alone never
     * moves anything.
     */
    public function update(string $taskId, UpdateTaskInput $input): UpdateTaskResult
    {
        return UpdateTaskResult::fromArray(
            $this->client->request('PATCH', '/tasks/' . \rawurlencode($taskId), $input->toArray()) ?? []
        );
    }

    /** Shorthand for an update that only changes the priority. */
    public function setPriority(string $taskId, float $priority): TaskPriority
    {
        return TaskPriority::fromArray(
            $this->client->request('PATCH', '/tasks/' . \rawurlencode($taskId), ['priority' => $priority]) ?? []
        );
    }

    /** Dry run: who *would* match these tags, without creating a task. */
    public function suggestWorkers(SuggestWorkers $input): SuggestWorkersResult
    {
        return SuggestWorkersResult::fromArray(
            $this->client->request('POST', '/tasks/suggest-workers', $input->toArray()) ?? []
        );
    }
}
