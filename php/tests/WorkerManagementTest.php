<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Model\UpsertWorker;

/**
 * Worker management: upsert, list, queue inspection, and removal.
 */
final class WorkerManagementTest extends ClientTestCase
{
    public function test_upserting_a_worker_without_an_id_gets_one_assigned(): void
    {
        $this->enqueueJson(200, '{"id":"w_abc"}');

        $workerId = $this->client()->workers()->upsert();

        self::assertSame('w_abc', $workerId);
        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function test_upserting_a_worker_with_tags_and_routing_weights_serializes_them(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert(
            (new UpsertWorker('agent_1'))
                ->tags(['english', 'billing'])
                ->routingWeights(['english' => 100.0]),
        );

        self::assertSame(
            ['id' => 'agent_1', 'tags' => ['english', 'billing'], 'routingWeights' => ['english' => 100.0]],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function test_upserting_a_field_worker_includes_geo_fields(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert(
            (new UpsertWorker('agent_1'))
                ->ip('203.0.113.5')
                ->latitude(59.4)
                ->longitude(24.7)
                ->maxTravelDistanceKm(15),
        );

        self::assertSame(
            [
                'id' => 'agent_1',
                'ip' => '203.0.113.5',
                'latitude' => 59.4,
                'longitude' => 24.7,
                'maxTravelDistanceKm' => 15.0,
            ],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function test_listing_workers_returns_ids_and_count(): void
    {
        $this->enqueueJson(200, '{"workers":["agent_1","agent_2"],"count":2}');

        $list = $this->client()->workers()->list();

        self::assertSame(['agent_1', 'agent_2'], $list->workers);
        self::assertSame(2, $list->count);
    }

    public function test_inspecting_a_worker_queue_returns_their_task_ids(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":["task_8fk2","task_9"]}');

        $queue = $this->client()->workers()->queue('agent_1');

        self::assertSame('agent_1', $queue->workerId);
        self::assertSame(['task_8fk2', 'task_9'], $queue->taskIds);
        self::assertSame('/v1/workers/agent_1/queue', $this->lastRequest()->getUri()->getPath());
    }

    public function test_removing_a_worker_hits_delete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->workers()->remove('agent_1');

        $req = $this->lastRequest();
        self::assertSame('DELETE', $req->getMethod());
        self::assertSame('/v1/workers/agent_1', $req->getUri()->getPath());
    }

    public function test_upserting_a_worker_at_the_plan_limit_raises(): void
    {
        $this->enqueueJson(402, '{"error":{"code":"plan_limit_exceeded","message":"worker limit of 25 reached"}}', [
            'X-Quota-Workers-Limit' => '25',
            'X-Quota-Workers-Remaining' => '0',
        ]);

        try {
            $this->client()->workers()->upsert(new UpsertWorker('agent_1'));
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(402, $e->statusCode);
            self::assertSame('plan_limit_exceeded', $e->apiCode);
            self::assertSame(25, $e->quota?->workersLimit);
            self::assertSame(0, $e->quota?->workersRemaining);
        }
    }
}
