<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\UnparkTask;

/**
 * Bulk create, the dry-run check, the escalation ladder, and the parked/scheduled views.
 *
 * This is the operational surface — reached for when a queue is misbehaving, not on the happy
 * path. Two things are pinned hard: bulk create reports partial success (so a caller reading only
 * the status code silently loses tasks), and unpark's reset flags decide whether the next sweep
 * parks the task straight back.
 */
final class TaskOperationsTest extends ClientTestCase
{
    private const TASK = '{"id":"task_8fk2","status":"queued","tags":["english"],'
        . '"priority":90,"createdAt":1754000000000}';

    // ---- bulk create ----

    public function testBulkCreateReportsPartialSuccessPerEntry(): void
    {
        // A 200 here does not mean everything was created. A caller that reads only the status
        // code loses the failures silently, so `failed` and the per-entry error must survive.
        $this->enqueueJson(200, '{"created":1,"failed":1,"results":['
            . '{"index":0,"id":"task_1","status":"queued"},'
            . '{"index":1,"error":{"code":"validation_failed","message":"tags required"}}]}');

        $report = $this->client()->tasks()->createMany([
            new CreateTask(['a']),
            new CreateTask(['b']),
        ]);

        $request = $this->lastRequest();
        self::assertSame('/v1/tasks/bulk', $this->pathOf($request));
        self::assertCount(2, $this->requestBodyJson($request)['tasks']);
        self::assertSame(1, $report->created);
        self::assertSame(1, $report->failed);
        self::assertTrue($report->results[0]->ok());
        self::assertSame('task_1', $report->results[0]->id);
        self::assertFalse($report->results[1]->ok());
        self::assertSame('validation_failed', $report->results[1]->error?->code);
    }

    public function testABulkEntryKeepsTheIndexThatMapsItBackToTheInput(): void
    {
        // The caller's list is the only way to know *which* task failed; without index a partial
        // failure is unactionable.
        $this->enqueueJson(200, '{"created":0,"failed":1,"results":['
            . '{"index":7,"error":{"code":"plan_limit_exceeded","message":"quota"}}]}');

        $report = $this->client()->tasks()->createMany([new CreateTask(['a'])]);

        self::assertSame(7, $report->results[0]->index);
    }

    // ---- dry-run check ----

    public function testCheckReportsWhoCouldTakeATaskWithoutCreatingIt(): void
    {
        $this->enqueueJson(200, '{"issues":[{"severity":"warning","code":"no_coverage",'
            . '"message":"nobody has welsh","tag":"welsh"}],"eligibleWorkerCount":0,'
            . '"uncoveredTags":["welsh"],"evaluatedAt":1754000000000}');

        $report = $this->client()->tasks()->check(new CreateTask(['welsh']));

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/tasks/check', $this->pathOf($request));
        self::assertSame(0, $report->eligibleWorkerCount);
        self::assertSame(['welsh'], $report->uncoveredTags);
        self::assertSame('welsh', $report->issues[0]->tag);
    }

    public function testACheckIssueWithoutATagParsesAsNull(): void
    {
        $this->enqueueJson(200, '{"issues":[{"severity":"error","code":"bad_sla",'
            . '"message":"negative"}],"eligibleWorkerCount":3,"uncoveredTags":[],"evaluatedAt":1}');

        $report = $this->client()->tasks()->check(new CreateTask(['a']));

        self::assertNull($report->issues[0]->tag);
    }

    // ---- ack / escalate / park ----

    public function testAckStopsTheResponseClockWithoutStartingWork(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"accepted"}');

        $this->client()->tasks()->ack('task_8fk2', 'agent_1');

        $request = $this->lastRequest();
        self::assertSame('/v1/tasks/task_8fk2/ack', $this->pathOf($request));
        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($request));
    }

    public function testEscalatingWithoutAWorkerSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"id":"t1","escalated":true,"parked":false,"escalationLevel":1}');

        $result = $this->client()->tasks()->escalate('t1');

        self::assertSame('{}', $this->requestBody($this->lastRequest()));
        self::assertSame(1, $result->escalationLevel);
        self::assertFalse($result->parked);
    }

    public function testAnExhaustedLadderReportsTheTaskAsParked(): void
    {
        // escalated:false + parked:true is the end of the ladder — the task left matching, and
        // this flag is the only thing that says so.
        $this->enqueueJson(200, '{"id":"t1","escalated":false,"parked":true,"escalationLevel":3}');

        $result = $this->client()->tasks()->escalate('t1', 'agent_1');

        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($this->lastRequest()));
        self::assertTrue($result->parked);
    }

    public function testParkedAndScheduledAreSeparateViewsEachWithACount(): void
    {
        $this->enqueueJson(200, '{"tasks":[' . self::TASK . '],"count":1}');
        $this->enqueueJson(200, '{"tasks":[' . self::TASK . '],"count":2}');

        $client = $this->client();
        $parked = $client->tasks()->parked();
        $scheduled = $client->tasks()->scheduled();

        $requests = $this->recordedRequests();
        self::assertSame('/v1/tasks/parked', $this->pathOf($requests[0]));
        self::assertSame('/v1/tasks/scheduled', $this->pathOf($requests[1]));
        self::assertSame(1, $parked->count);
        self::assertSame(2, $scheduled->count);
        self::assertSame('task_8fk2', $parked->tasks[0]->id);
    }

    public function testUnparkingWithNoOptionsSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"id":"t1","status":"queued"}');

        $this->client()->tasks()->unpark('t1');

        self::assertSame('{}', $this->requestBody($this->lastRequest()));
    }

    public function testUnparkResetFlagsReachTheWireAndUnsetOnesStayAbsent(): void
    {
        // Leave a clock set and the next sweep parks the task again — these flags are the whole
        // reason unpark is not just a status change. Absent differs from false.
        $this->enqueueJson(200, '{"id":"t1","status":"queued"}');

        $this->client()->tasks()->unpark('t1', (new UnparkTask())->resetEscalation(true)->resetSla(true));

        self::assertSame(
            ['resetEscalation' => true, 'resetSla' => true],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testAnExplicitFalseResetFlagIsSentRatherThanDropped(): void
    {
        // false actively says "do not reset"; only *unset* may be omitted.
        $this->enqueueJson(200, '{"id":"t1","status":"queued"}');

        $this->client()->tasks()->unpark('t1', (new UnparkTask())->resetSchedule(false));

        self::assertSame(['resetSchedule' => false], $this->requestBodyJson($this->lastRequest()));
    }

    // ---- SLA stats and the queue audit ----

    public function testSlaStatsDefaultToTheWholeWorkspace(): void
    {
        $this->enqueueJson(200, '{"tag":null,"offers":10,"acceptedInTime":9}');

        $stats = $this->client()->slaStats();

        $request = $this->lastRequest();
        self::assertSame('/v1/stats/sla', $this->pathOf($request));
        self::assertSame('', $this->queryOf($request));
        self::assertNull($stats->tag);
        self::assertSame(10, $stats->offers);
    }

    public function testSlaStatsForOneTagSendItAsAQueryParam(): void
    {
        $this->enqueueJson(200, '{"tag":"billing","offers":4,"acceptanceRate":0.75}');

        $stats = $this->client()->slaStats('billing');

        self::assertSame('tag=billing', $this->queryOf($this->lastRequest()));
        self::assertSame(0.75, $stats->acceptanceRate);
    }

    public function testNoOffersYetLeavesAcceptanceRateNullRatherThanZero(): void
    {
        // 0.0 means "everyone missed"; null means "nothing measured". Collapsing them turns a
        // cold start into a false alarm on a dashboard.
        $this->enqueueJson(200, '{"tag":null,"offers":0,"acceptanceRate":null}');

        self::assertNull($this->client()->slaStats()->acceptanceRate);
    }

    public function testTheQueueAuditNamesWhatIsBlockingEachTask(): void
    {
        $this->enqueueJson(200, '{"evaluatedAt":1754000000000,"scanned":2,"entries":['
            . '{"taskId":"t1","tags":["welsh"],"waitingMs":90000,"eligibleWorkerCount":0,'
            . '"uncoveredTags":["welsh"],"blockers":{"backlog_full":2,"paused":1}}],'
            . '"sweepBacklog":{"scheduleActivations":1,"scheduleMisses":0,"responseDeadlines":4,'
            . '"completionDeadlines":0,"slaExpiries":2}}');

        $report = $this->client()->queueAudit(limit: 10, minWaitingMs: 60000, includeHealthy: false);

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('limit=10', $query);
        self::assertStringContainsString('minWaitingMs=60000', $query);
        // Fastify parses the string form; PHP would otherwise render false as the empty string.
        self::assertStringContainsString('includeHealthy=false', $query);
        self::assertSame(2, $report->entries[0]->blockers['backlog_full']);
        self::assertSame(4, $report->sweepBacklog->responseDeadlines);
    }

    public function testANeverQueuedTaskHasANullWaitRatherThanZero(): void
    {
        $this->enqueueJson(200, '{"evaluatedAt":1,"scanned":1,"entries":[{"taskId":"t1","tags":[],'
            . '"waitingMs":null,"eligibleWorkerCount":1,"uncoveredTags":[],"blockers":{}}],'
            . '"sweepBacklog":{}}');

        $report = $this->client()->queueAudit();

        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertNull($report->entries[0]->waitingMs);
        self::assertSame(0, $report->sweepBacklog->slaExpiries);
    }

    // ---- portal link, learning revert, skills get ----

    public function testThePortalLinkReportsADisabledPortalWithoutErroring(): void
    {
        // A workspace with no published bundle is a normal state, not a failure.
        $this->enqueueJson(200, '{"portalEnabled":false,"portalUrl":"https://5xer.com/portal/ws_1/",'
            . '"exists":false,"version":null,"template":null,"publishedAt":null}');

        $link = $this->client()->portal();

        self::assertSame('/v1/portal', $this->pathOf($this->lastRequest()));
        self::assertFalse($link->portalEnabled);
        self::assertNull($link->version);
    }

    public function testRevertingLearnedWeightsWithoutIdsSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"reverted":["agent_1"],"count":1}');

        $result = $this->client()->learning()->revertWeights();

        $request = $this->lastRequest();
        self::assertSame('/v1/learning/weights/revert', $this->pathOf($request));
        self::assertSame('{}', $this->requestBody($request));
        self::assertSame(1, $result->count);
        self::assertSame(['agent_1'], $result->reverted);
    }

    public function testRevertingScopedToNamedWorkersSendsTheList(): void
    {
        $this->enqueueJson(200, '{"reverted":["agent_1"],"count":1}');

        $this->client()->learning()->revertWeights(['agent_1', 'agent_2']);

        self::assertSame(
            ['workerIds' => ['agent_1', 'agent_2']],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testFetchingOneSkillById(): void
    {
        $this->enqueueJson(200, '{"id":"sk_1","key":"welsh","name":"Welsh","createdAt":"x"}');

        self::assertSame('welsh', $this->client()->skills()->get('sk_1')->key);
        self::assertSame('/v1/skills/sk_1', $this->pathOf($this->lastRequest()));
    }
}
