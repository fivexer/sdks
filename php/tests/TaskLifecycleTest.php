<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Model\CreateTask;

/**
 * Task lifecycle: creating, reading, listing, cancelling, and transitioning tasks.
 * Black-box: drive $client->tasks()->* and assert the observable result plus the exact
 * HTTP recorded by the mock handler.
 */
final class TaskLifecycleTest extends ClientTestCase
{
    public function test_creating_a_task_with_tags_returns_a_queued_task(): void
    {
        $this->enqueueJson(202, '{"id":"task_8fk2","status":"queued"}');

        $task = $this->client()->tasks()->create(new CreateTask(['english', 'billing']));

        self::assertSame('task_8fk2', $task->id);
        self::assertSame('queued', $task->status);

        $req = $this->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('/v1/tasks', $req->getUri()->getPath());
        self::assertSame('Bearer sk_test_abc123', $req->getHeaderLine('Authorization'));
        self::assertSame(['tags' => ['english', 'billing']], $this->requestBodyJson($req));
    }

    public function test_creating_a_task_auto_attaches_an_idempotency_key(): void
    {
        $this->enqueueJson(202, '{"id":"task_8fk2","status":"queued"}');

        $this->client()->tasks()->create(new CreateTask(['english']));

        $key = $this->lastRequest()->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $key,
            'idempotency key should be an RFC 4122 v4 UUID',
        );
    }

    public function test_creating_a_task_with_priority_meta_and_vetoes_serializes_every_field(): void
    {
        $this->enqueueJson(202, '{"id":"task_9","status":"queued"}');

        $this->client()->tasks()->create(
            (new CreateTask(['english']))
                ->id('task_9')
                ->priority(90)
                ->meta(['ticketId' => 'T-441'])
                ->vetoedWorkers(['w_123'])
                ->skillThresholds(['english' => 5.0]),
        );

        self::assertSame([
            'tags' => ['english'],
            'id' => 'task_9',
            'priority' => 90.0,
            'skillThresholds' => ['english' => 5.0],
            'vetoedWorkers' => ['w_123'],
            'meta' => ['ticketId' => 'T-441'],
        ], $this->requestBodyJson($this->lastRequest()));
    }

    public function test_creating_a_task_rejects_empty_tags(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateTask([]);
    }

    public function test_getting_a_task_returns_parsed_fields(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","tags":["english","billing"],"priority":90,'
            . '"status":"pending","workerId":"agent_1","createdAt":1750000000000,'
            . '"meta":{"ticketId":"T-441"}}');

        $task = $this->client()->tasks()->get('task_8fk2');

        self::assertSame('task_8fk2', $task->id);
        self::assertSame(['english', 'billing'], $task->tags);
        self::assertSame(90.0, $task->priority);
        self::assertSame('pending', $task->status);
        self::assertSame('agent_1', $task->workerId);
        self::assertSame(1750000000000, $task->createdAt);
        self::assertSame(['ticketId' => 'T-441'], $task->meta);
        self::assertSame('/v1/tasks/task_8fk2', $this->lastRequest()->getUri()->getPath());
    }

    public function test_getting_a_missing_task_raises_not_found(): void
    {
        $this->enqueueJson(404, '{"error":{"code":"not_found","message":"task not found"}}');

        try {
            $this->client()->tasks()->get('missing');
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame('not_found', $e->apiCode);
            self::assertSame('task not found', $e->getMessage());
        }
    }

    public function test_listing_tasks_passes_status_cursor_and_limit_as_query(): void
    {
        $this->enqueueJson(200, '{"tasks":[],"nextCursor":null,"hasMore":false}');

        $this->client()->tasks()->list(status: 'queued', cursor: 'c1', limit: 50);

        self::assertSame('status=queued&cursor=c1&limit=50', $this->lastRequest()->getUri()->getQuery());
    }

    public function test_listing_tasks_paginates_and_parses_cursor(): void
    {
        $this->enqueueJson(200, '{"tasks":[{"id":"t1","tags":["x"],"priority":1,"status":"queued",'
            . '"workerId":null,"createdAt":1,"meta":null}],"nextCursor":"cursor_1","hasMore":true}');

        $page = $this->client()->tasks()->list();

        self::assertTrue($page->hasMore);
        self::assertSame('cursor_1', $page->nextCursor);
        self::assertSame('t1', $page->tasks[0]->id);
    }

    public function test_cancelling_a_task_returns_nothing_and_hits_delete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->cancel('task_8fk2');

        $req = $this->lastRequest();
        self::assertSame('DELETE', $req->getMethod());
        self::assertSame('/v1/tasks/task_8fk2', $req->getUri()->getPath());
    }

    public function test_a_worker_accepts_a_pending_task(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"accepted"}');

        $action = $this->client()->tasks()->accept('task_8fk2', 'agent_1');

        self::assertSame('accepted', $action->status);
        self::assertSame('task_8fk2', $action->id);
        $req = $this->lastRequest();
        self::assertSame('/v1/tasks/task_8fk2/accept', $req->getUri()->getPath());
        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($req));
    }

    public function test_a_worker_rejects_a_task_so_it_is_requeued(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"queued"}');

        $action = $this->client()->tasks()->reject('task_8fk2', 'agent_1');

        self::assertSame('queued', $action->status);
        self::assertSame('/v1/tasks/task_8fk2/reject', $this->lastRequest()->getUri()->getPath());
    }

    public function test_a_worker_completes_a_task_with_a_result(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"completed"}');

        $action = $this->client()->tasks()->complete('task_8fk2', 'agent_1', ['resolved' => true]);

        self::assertSame('completed', $action->status);
        self::assertSame(
            ['workerId' => 'agent_1', 'result' => ['resolved' => true]],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function test_completing_a_task_without_a_result_omits_the_field(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"completed"}');

        $this->client()->tasks()->complete('task_8fk2', 'agent_1');

        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($this->lastRequest()));
    }

    public function test_base_url_trailing_slash_is_stripped(): void
    {
        $client = new \Fivexer\SDK\Fivexer('https://api.fivexer.test/', 'sk_x');
        self::assertSame('https://api.fivexer.test', $client->getBaseUrl());
    }
}
