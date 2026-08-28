<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\AssignTaskResult;
use Fivexer\SDK\Model\BulkTaskReport;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\TaskCheckReport;
use Fivexer\SDK\Model\TaskEscalation;
use Fivexer\SDK\Model\TaskList;
use Fivexer\SDK\Model\UnparkTask;
use Fivexer\SDK\Model\SuggestWorkers;
use Fivexer\SDK\Model\SuggestWorkersResult;
use Fivexer\SDK\Model\Task;
use Fivexer\SDK\Model\TaskAction;
use Fivexer\SDK\Model\TaskPage;
use Fivexer\SDK\Model\TaskPriority;

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

    public function __construct(private readonly Fivexer $client)
    {
        $this->contextResource = new TaskContexts($client);
        $this->commentsResource = new TaskComments($client);
        $this->attachmentsResource = new TaskAttachments($client);
        $this->recurringResource = new TaskRecurring($client);
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
