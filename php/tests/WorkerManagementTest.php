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

    public function test_one_workers_metrics_report_today_and_the_rolling_window(): void
    {
        // The operator's copy of what the worker sees about themselves — same numbers, one log.
        $this->enqueueJson(200, '{"workerId":"agent_1","today":{"since":"2026-07-24T00:00:00Z",'
            . '"completedTasks":7,"shiftCount":1,"onShiftMs":28800000,"breakCount":1,'
            . '"totalBreakMs":1800000,"longestBreakMs":1800000,"workingMs":27000000},'
            . '"window":{"from":"2026-07-17T00:00:00Z","to":"2026-07-24T00:00:00Z",'
            . '"days":[{"day":"2026-07-23","completed":12}],"medianWaitMs":4200,'
            . '"medianCycleMs":90000,"shiftCount":5,"onShiftMs":144000000,"breakMs":9000000,'
            . '"workingMs":135000000,"offered":20,"accepted":16,"rejected":2,"completed":14,'
            . '"failed":1,"expired":1,"released":0,"acceptanceRate":0.8}}');

        $metrics = $this->client()->workers()->metrics('agent_1', '7d');

        $request = $this->lastRequest();
        self::assertSame('/v1/workers/agent_1/metrics', $this->pathOf($request));
        self::assertSame('window=7d', $this->queryOf($request));
        self::assertSame('agent_1', $metrics->workerId);
        self::assertSame('2026-07-24T00:00:00Z', $metrics->today->since);
        self::assertSame(7, $metrics->today->completedTasks);
        self::assertSame(1, $metrics->today->shiftCount);
        self::assertSame(28800000, $metrics->today->onShiftMs);
        self::assertSame(1, $metrics->today->breakCount);
        self::assertSame(1800000, $metrics->today->totalBreakMs);
        self::assertSame(1800000, $metrics->today->longestBreakMs);
        // Breaks are time inside a shift: 8h on shift less a 30m break is 7h30 worked.
        self::assertSame(27000000, $metrics->today->workingMs);
        self::assertSame('2026-07-17T00:00:00Z', $metrics->window->from);
        self::assertSame('2026-07-24T00:00:00Z', $metrics->window->to);
        self::assertSame('2026-07-23', $metrics->window->days[0]->day);
        self::assertSame(4200, $metrics->window->medianWaitMs);
        self::assertSame(90000, $metrics->window->medianCycleMs);
        self::assertSame(5, $metrics->window->shiftCount);
        self::assertSame(144000000, $metrics->window->onShiftMs);
        self::assertSame(9000000, $metrics->window->breakMs);
        self::assertSame(135000000, $metrics->window->workingMs);
        self::assertSame(20, $metrics->window->offered);
        self::assertSame(16, $metrics->window->accepted);
        self::assertSame(2, $metrics->window->rejected);
        self::assertSame(14, $metrics->window->completed);
        self::assertSame(1, $metrics->window->failed);
        self::assertSame(1, $metrics->window->expired);
        self::assertSame(0, $metrics->window->released);
        self::assertSame(0.8, $metrics->window->acceptanceRate);
    }

    public function test_a_worker_who_was_never_offered_anything_has_no_acceptance_rate(): void
    {
        // Null, not 0.0 — "nothing was measured" and "they accepted none of it" differ.
        $this->enqueueJson(200, '{"workerId":"agent_9","today":{"since":"x"},'
            . '"window":{"from":"a","to":"b","days":null,"medianWaitMs":null,'
            . '"medianCycleMs":null,"acceptanceRate":null}}');

        $metrics = $this->client()->workers()->metrics('agent_9');

        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertNull($metrics->window->acceptanceRate);
        self::assertNull($metrics->window->medianWaitMs);
        self::assertNull($metrics->window->medianCycleMs);
        self::assertNull($metrics->window->days);
        self::assertSame(0, $metrics->today->completedTasks);
    }

    public function test_the_time_entries_log_records_shifts_and_the_breaks_inside_them(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","from":"2026-07-17T00:00:00Z",'
            . '"to":"2026-07-24T00:00:00Z","entries":['
            . '{"type":"shift","startedAt":"2026-07-23T08:00:00Z",'
            . '"endedAt":"2026-07-23T16:00:00Z","durationMs":28800000,"source":"portal",'
            . '"endReason":"manual"},'
            . '{"type":"break","startedAt":"2026-07-23T11:30:00Z",'
            . '"endedAt":"2026-07-23T12:00:00Z","durationMs":1800000,"reason":"lunch"}],'
            . '"totals":{"shiftCount":1,"onShiftMs":28800000,"breakCount":1,"breakMs":1800000,'
            . '"workingMs":27000000}}');

        $log = $this->client()->workers()
            ->timeEntries('agent_1', '2026-07-17T00:00:00Z', '2026-07-24T00:00:00Z');

        $request = $this->lastRequest();
        self::assertSame('/v1/workers/agent_1/time-entries', $this->pathOf($request));
        self::assertStringContainsString('from=2026-07-17', $this->queryOf($request));
        self::assertStringContainsString('to=2026-07-24', $this->queryOf($request));
        self::assertSame('agent_1', $log->workerId);
        self::assertSame('2026-07-17T00:00:00Z', $log->from);
        self::assertSame('2026-07-24T00:00:00Z', $log->to);
        self::assertSame('shift', $log->entries[0]->type);
        self::assertSame('2026-07-23T08:00:00Z', $log->entries[0]->startedAt);
        self::assertSame('2026-07-23T16:00:00Z', $log->entries[0]->endedAt);
        self::assertSame(28800000, $log->entries[0]->durationMs);
        self::assertSame('portal', $log->entries[0]->source);
        self::assertSame('manual', $log->entries[0]->endReason);
        self::assertSame('break', $log->entries[1]->type);
        self::assertSame('lunch', $log->entries[1]->reason);
        self::assertNull($log->entries[1]->source);
        self::assertSame(1, $log->totals->shiftCount);
        self::assertSame(28800000, $log->totals->onShiftMs);
        self::assertSame(1, $log->totals->breakCount);
        self::assertSame(1800000, $log->totals->breakMs);
        // workingMs is onShiftMs - breakMs: a break is time inside the shift, not beside it.
        self::assertSame(27000000, $log->totals->workingMs);
    }

    public function test_a_shift_the_platform_closed_says_so_and_an_open_one_has_no_end(): void
    {
        // `timeout` means the platform clocked out a silent unattended worker, not a judgement.
        $this->enqueueJson(200, '{"workerId":"agent_1","from":"a","to":"b","entries":['
            . '{"type":"shift","startedAt":"2026-07-23T08:00:00Z","endedAt":null,'
            . '"durationMs":600000,"source":"portal","endReason":null},'
            . '{"type":"shift","startedAt":"2026-07-22T08:00:00Z",'
            . '"endedAt":"2026-07-22T09:00:00Z","durationMs":3600000,"source":"portal",'
            . '"endReason":"timeout"}],"totals":{}}');

        $log = $this->client()->workers()->timeEntries('agent_1');

        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertNull($log->entries[0]->endedAt);
        self::assertNull($log->entries[0]->endReason);
        self::assertSame('timeout', $log->entries[1]->endReason);
        self::assertSame(0, $log->totals->shiftCount);
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
