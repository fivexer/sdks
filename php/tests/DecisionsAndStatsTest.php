<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

/**
 * Decision traces (explainability) and workspace stats.
 */
final class DecisionsAndStatsTest extends ClientTestCase
{
    public function test_querying_decisions_by_task_returns_candidates_with_scores(): void
    {
        $this->enqueueJson(200, '{"decisions":[{"id":"dec_1","taskId":"task_8fk2",'
            . '"workerId":"agent_1","matchedAt":1750000000000,"mode":"best-match",'
            . '"candidates":[{"workerId":"agent_1","eligible":true,"chosen":true,"score":101},'
            . '{"workerId":"agent_2","eligible":false,"chosen":false,"score":0}]}]}');

        $decisions = $this->client()->decisions()->list(taskId: 'task_8fk2');

        self::assertCount(1, $decisions);
        $dec = $decisions[0];
        self::assertSame('dec_1', $dec->id);
        self::assertSame('task_8fk2', $dec->taskId);
        self::assertSame('agent_1', $dec->workerId);
        self::assertSame(1750000000000, $dec->matchedAt);
        self::assertSame('best-match', $dec->mode);
        self::assertCount(2, $dec->candidates);
        // workerId is pulled out; the rest lands verbatim in detail
        self::assertSame('agent_1', $dec->candidates[0]->workerId);
        self::assertSame(['eligible' => true, 'chosen' => true, 'score' => 101], $dec->candidates[0]->detail);
        self::assertStringContainsString('taskId=task_8fk2', $this->lastRequest()->getUri()->getQuery());
    }

    public function test_querying_decisions_by_worker_and_limit_passes_them(): void
    {
        $this->enqueueJson(200, '{"decisions":[]}');

        $decisions = $this->client()->decisions()->list(workerId: 'agent_1', limit: 10);

        self::assertSame([], $decisions);
        $query = $this->lastRequest()->getUri()->getQuery();
        self::assertStringContainsString('workerId=agent_1', $query);
        self::assertStringContainsString('limit=10', $query);
    }

    public function test_decisions_default_to_an_empty_list_when_none_exist(): void
    {
        $this->enqueueJson(200, '{"decisions":[]}');

        self::assertSame([], $this->client()->decisions()->list());
    }

    public function test_workspace_stats_reports_queue_depth_meter_and_plan(): void
    {
        $this->enqueueJson(200, '{"plan":"pro","tasks":{"queued":3,"pending":1,"accepted":2},'
            . '"workers":5,"meter":{"period":"2026-07","matchedTasks":421,'
            . '"includedTasksPerMonth":50000}}');

        $stats = $this->client()->stats();

        self::assertSame('pro', $stats->plan);
        self::assertSame(['queued' => 3, 'pending' => 1, 'accepted' => 2], $stats->tasks);
        self::assertSame(5, $stats->workers);
        self::assertSame('2026-07', $stats->meterPeriod);
        self::assertSame(421, $stats->meterMatchedTasks);
        self::assertSame(50000, $stats->meterIncludedTasksPerMonth);
        self::assertSame('/v1/stats', $this->lastRequest()->getUri()->getPath());
    }
}
