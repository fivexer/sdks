<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\SetTaskContext;
use Fivexer\SDK\Model\TaskContext;

/** The rich data stored against a task. Writes replace the stored context wholesale. */
final class TaskContexts
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function get(string $taskId): TaskContext
    {
        return TaskContext::fromArray(
            $this->client->request('GET', '/tasks/' . \rawurlencode($taskId) . '/context') ?? []
        );
    }

    public function set(string $taskId, SetTaskContext $input): TaskContext
    {
        return TaskContext::fromArray(
            $this->client->request('PUT', '/tasks/' . \rawurlencode($taskId) . '/context', $input->toArray()) ?? []
        );
    }

    public function clear(string $taskId): void
    {
        $this->client->request('DELETE', '/tasks/' . \rawurlencode($taskId) . '/context');
    }
}
