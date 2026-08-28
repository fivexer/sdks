<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\FivexerWorker;
use Fivexer\SDK\Model\WorkerLogin;

/**
 * The worker portal plane: one worker acting on their own queue with a `wt_` token.
 *
 * The security property this plane exists for is that the token is scoped to a single worker and
 * cannot reach task creation or worker management. These tests hold the client to its half of
 * that: it never sends a workspace key, and it will not guess a worker id it was not given.
 */
final class WorkerPortalTest extends ClientTestCase
{
    // ---- login / logout ----

    public function testLoggingInAdoptsTheTokenAndTheWorkerId(): void
    {
        $this->enqueueJson(200, '{"token":"wt_s3ss10n"}');
        $worker = $this->anonymousWorker();

        $session = $worker->login(new WorkerLogin('ws_1', 'agent_1', '4821'));

        $request = $this->lastRequest();
        self::assertSame('/v1/worker-auth/login', $this->pathOf($request));
        self::assertSame(
            ['workspaceId' => 'ws_1', 'workerId' => 'agent_1', 'pin' => '4821'],
            $this->requestBodyJson($request),
        );
        self::assertSame('wt_s3ss10n', $session->token);
        self::assertSame('wt_s3ss10n', $worker->getSessionToken());
        self::assertSame('agent_1', $worker->getWorkerId());
    }

    public function testTheLoginRequestItselfCarriesNoAuthorization(): void
    {
        // There is no token yet; sending an empty bearer would be a malformed request.
        $this->enqueueJson(200, '{"token":"wt_s3ss10n"}');

        $this->anonymousWorker()->login(new WorkerLogin('ws_1', 'agent_1', '4821'));

        self::assertSame('', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testAWrongPinLeavesTheClientUnauthenticated(): void
    {
        $this->enqueueJson(401, '{"error":{"code":"invalid_credentials","message":"bad pin"}}');
        $worker = $this->anonymousWorker();

        try {
            $worker->login(new WorkerLogin('ws_1', 'agent_1', '0000'));
            self::fail('expected the login to be rejected');
        } catch (FivexerApiException $e) {
            self::assertSame('invalid_credentials', $e->apiCode);
        }

        self::assertNull($worker->getSessionToken());
    }

    public function testLoggingOutForgetsTheToken(): void
    {
        $this->enqueueEmpty(204);
        $worker = $this->worker();

        $worker->logout();

        self::assertSame('/v1/worker-auth/logout', $this->pathOf($this->lastRequest()));
        self::assertNull($worker->getSessionToken());
    }

    public function testAResumedSessionAuthenticatesWithTheStoredToken(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":[]}');

        $this->worker()->queue();

        self::assertSame('Bearer wt_s3ss10n', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testATokenCanBeAdoptedAfterConstruction(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_7","taskIds":[]}');
        $worker = $this->anonymousWorker();

        $worker->setToken('wt_restored', 'agent_7');
        $worker->queue();

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/workers/agent_7/queue', $this->pathOf($request));
        self::assertSame('Bearer wt_restored', $request->getHeaderLine('Authorization'));
    }

    public function testAdoptingATokenWithoutAWorkerIdKeepsTheExistingOne(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":[]}');
        $worker = $this->worker();

        $worker->setToken('wt_rotated');
        $worker->queue();

        self::assertSame('/v1/portal/workers/agent_1/queue', $this->pathOf($this->lastRequest()));
    }

    public function testTheClientExposesItsBaseUrlWithoutATrailingSlash(): void
    {
        self::assertSame(
            'https://api.fivexer.test',
            (new FivexerWorker('https://api.fivexer.test/'))->getBaseUrl(),
        );
    }

    public function testABaseUrlIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FivexerWorker('');
    }

    // ---- acting without a worker id ----

    public function testActingBeforeLoggingInFailsWithoutReachingTheNetwork(): void
    {
        // Guessing an id here would let one worker act as another; refusing locally is the point.
        $worker = $this->anonymousWorker();

        try {
            $worker->queue();
            self::fail('expected the client to refuse without a worker id');
        } catch (FivexerApiException $e) {
            self::assertSame('worker_id_required', $e->apiCode);
            self::assertSame(400, $e->statusCode);
        }

        self::assertSame([], $this->history);
    }

    public function testAnEmptyWorkerIdIsTreatedAsNoWorkerId(): void
    {
        // A blank string would build /portal/workers//queue and 404 confusingly.
        $worker = new FivexerWorker('https://api.fivexer.test', 'wt_1', '');

        $this->expectException(FivexerApiException::class);
        $worker->queue();
    }

    public function testAnExplicitWorkerIdWorksWithoutAPriorLogin(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"accepted"}');

        $this->anonymousWorker()->accept('task_8fk2', 'agent_9');

        self::assertSame('agent_9', $this->requestBodyJson($this->lastRequest())['workerId']);
    }

    // ---- queue and task actions ----

    public function testAWorkerReadsTheirOwnQueue(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":["task_8fk2"]}');

        $queue = $this->worker()->queue();

        self::assertSame('/v1/portal/workers/agent_1/queue', $this->pathOf($this->lastRequest()));
        self::assertSame(['task_8fk2'], $queue->taskIds);
    }

    public function testReadingAQueueForAnExplicitlyNamedWorkerIgnoresTheSessionWorker(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_9","taskIds":[]}');

        $this->worker()->queue('agent_9');

        self::assertSame('/v1/portal/workers/agent_9/queue', $this->pathOf($this->lastRequest()));
    }

    public function testTaskDetailIncludesTheRichContextTheWorkerNeeds(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"pending","tags":["english"],'
            . '"priority":90,"title":"Refund request","description":"Order 41",'
            . '"context":{"orderId":"41"},'
            . '"references":[{"id":"ref_1","url":"https://crm/o/41","label":"Order 41"}],'
            . '"createdAt":1750000000000}');

        $detail = $this->worker()->taskDetail('task_8fk2');

        self::assertSame('Refund request', $detail->title);
        self::assertSame('Order 41', $detail->description);
        self::assertSame(90.0, $detail->priority);
        self::assertSame(['english'], $detail->tags);
        self::assertSame(['orderId' => '41'], $detail->context);
        self::assertNotNull($detail->references);
        self::assertSame('Order 41', $detail->references[0]->label);
        self::assertSame(1750000000000, $detail->createdAt);
    }

    public function testAnotherWorkersTaskIsNotVisible(): void
    {
        $this->enqueueJson(404, '{"error":{"code":"not_found","message":"task not found"}}');

        try {
            $this->worker()->taskDetail('task_other');
            self::fail('expected another worker\'s task to be invisible');
        } catch (FivexerApiException $e) {
            self::assertSame(404, $e->statusCode);
        }
    }

    public function testAcceptingATaskReportsTheNewStatus(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"accepted"}');

        $result = $this->worker()->accept('task_8fk2');

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/tasks/task_8fk2/accept', $this->pathOf($request));
        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($request));
        self::assertSame('accepted', $result->status);
        self::assertSame('task_8fk2', $result->id);
    }

    public function testRejectingATaskRequeuesItForOthers(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"queued"}');

        self::assertSame('queued', $this->worker()->reject('task_8fk2')->status);
        self::assertSame('/v1/portal/tasks/task_8fk2/reject', $this->pathOf($this->lastRequest()));
    }

    public function testCompletingATaskCanAttachAResult(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"completed"}');

        $result = $this->worker()->complete('task_8fk2', ['refunded' => true]);

        self::assertSame('completed', $result->status);
        self::assertSame(
            ['workerId' => 'agent_1', 'result' => ['refunded' => true]],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function testCompletingWithoutAResultOmitsTheField(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"completed"}');

        $this->worker()->complete('task_8fk2');

        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($this->lastRequest()));
    }

    public function testCompletingOnBehalfOfAnExplicitWorkerUsesThatId(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"completed"}');

        $this->worker()->complete('task_8fk2', null, 'agent_9');

        self::assertSame('agent_9', $this->requestBodyJson($this->lastRequest())['workerId']);
    }

    // ---- breaks ----

    public function testStartingABreakPausesRoutingToThisWorker(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","onBreak":true,"since":"2026-07-24T11:30:00Z"}');

        $started = $this->worker()->startBreak('lunch');

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/breaks/start', $this->pathOf($request));
        self::assertSame(['reason' => 'lunch'], $this->requestBodyJson($request));
        self::assertTrue($started->onBreak);
        self::assertSame('2026-07-24T11:30:00Z', $started->since);
    }

    public function testStartingABreakWithoutAReasonSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","onBreak":true,"since":"x"}');

        $this->worker()->startBreak();

        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function testStartingASecondBreakIsRefused(): void
    {
        $this->enqueueJson(409, '{"error":{"code":"break_already_open","message":"already open"}}');

        try {
            $this->worker()->startBreak();
            self::fail('expected a second break to be refused');
        } catch (FivexerApiException $e) {
            self::assertSame(409, $e->statusCode);
            self::assertSame('break_already_open', $e->apiCode);
        }
    }

    public function testEndingABreakResumesRouting(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","onBreak":false,"endedAt":"2026-07-24T12:00:00Z"}');

        $ended = $this->worker()->endBreak();

        self::assertNotNull($ended);
        self::assertFalse($ended->onBreak);
        self::assertSame('2026-07-24T12:00:00Z', $ended->endedAt);
    }

    public function testEndingABreakWhenNoneIsOpenIsNotAnError(): void
    {
        // The API answers 204; a portal UI calling this on a timer must not see an exception.
        $this->enqueueEmpty(204);

        self::assertNull($this->worker()->endBreak());
    }

    public function testTodaysBreaksIncludeTheOneStillRunning(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"2026-07-24T00:00:00Z","breaks":[],'
            . '"active":{"id":"brk_2","startedAt":"2026-07-24T13:00:00Z","endedAt":null,'
            . '"reason":null,"durationMs":0},"completedTasks":7,"totalBreakMs":0}');

        $today = $this->worker()->breaksToday();

        self::assertSame('/v1/portal/breaks/today', $this->pathOf($this->lastRequest()));
        self::assertNotNull($today->active);
        self::assertSame('brk_2', $today->active->id);
        self::assertSame(7, $today->completedTasks);
    }

    public function testTodaysMetricsSeparateWorkingTimeFromBreakTime(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"2026-07-24T00:00:00Z",'
            . '"completedTasks":7,"breakCount":1,"totalBreakMs":1800000,'
            . '"longestBreakMs":1800000,"workingMs":25200000}');

        $metrics = $this->worker()->metricsToday();

        self::assertSame('/v1/portal/metrics/today', $this->pathOf($this->lastRequest()));
        self::assertSame(25200000, $metrics->workingMs);
        self::assertSame(1800000, $metrics->totalBreakMs);
        self::assertSame(1800000, $metrics->longestBreakMs);
        self::assertSame(1, $metrics->breakCount);
        self::assertSame(7, $metrics->completedTasks);
    }

    public function testTodaysMetricsAlsoReportShiftTimeWhenTheServerHasAShiftLog(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"2026-07-24T00:00:00Z",'
            . '"completedTasks":7,"breakCount":1,"totalBreakMs":1800000,'
            . '"longestBreakMs":1800000,"workingMs":27000000,"onShiftMs":28800000,'
            . '"shiftCount":1}');

        $metrics = $this->worker()->metricsToday();

        self::assertSame(28800000, $metrics->onShiftMs);
        self::assertSame(1, $metrics->shiftCount);
        self::assertSame(27000000, $metrics->workingMs);
    }

    public function testAnOlderServerWithoutAShiftLogReportsNoShiftTime(): void
    {
        // onShiftMs/shiftCount are additive: absent means the deployment predates the shift log.
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"x","completedTasks":0,'
            . '"breakCount":0,"totalBreakMs":0,"longestBreakMs":0,"workingMs":100}');

        $metrics = $this->worker()->metricsToday();

        self::assertSame(0, $metrics->onShiftMs);
        self::assertSame(0, $metrics->shiftCount);
    }

    public function testAWorkerReadsTheirOwnTimeLogWithNoParametersToNarrowIt(): void
    {
        // Same record an operator reads — that parity is what keeps it a timesheet.
        $this->enqueueJson(200, '{"workerId":"agent_1","from":"2026-07-17T00:00:00Z",'
            . '"to":"2026-07-24T00:00:00Z","entries":['
            . '{"type":"shift","startedAt":"2026-07-23T08:00:00Z",'
            . '"endedAt":"2026-07-23T16:00:00Z","durationMs":28800000,"source":"portal",'
            . '"endReason":"manual"},'
            . '{"type":"break","startedAt":"2026-07-23T11:30:00Z",'
            . '"endedAt":"2026-07-23T12:00:00Z","durationMs":1800000,"reason":"lunch"}],'
            . '"totals":{"shiftCount":1,"onShiftMs":28800000,"breakCount":1,"breakMs":1800000,'
            . '"workingMs":27000000}}');

        $log = $this->worker()->timeEntries();

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/me/time-entries', $this->pathOf($request));
        self::assertSame('GET', $request->getMethod());
        self::assertSame('', $this->queryOf($request));
        self::assertSame('agent_1', $log->workerId);
        self::assertSame('2026-07-17T00:00:00Z', $log->from);
        self::assertSame('2026-07-24T00:00:00Z', $log->to);
        self::assertSame('shift', $log->entries[0]->type);
        self::assertSame('portal', $log->entries[0]->source);
        self::assertSame('manual', $log->entries[0]->endReason);
        self::assertSame(28800000, $log->entries[0]->durationMs);
        self::assertSame('lunch', $log->entries[1]->reason);
        self::assertSame(1, $log->totals->shiftCount);
        self::assertSame(28800000, $log->totals->onShiftMs);
        self::assertSame(1, $log->totals->breakCount);
        self::assertSame(1800000, $log->totals->breakMs);
        self::assertSame(27000000, $log->totals->workingMs);
    }

    public function testAWorkerCanSeeWhoElseIsOnShift(): void
    {
        $this->enqueueJson(200, '{"workers":[{"workerId":"agent_1","label":"Ada","status":"working"}],'
            . '"counts":{"working":1,"onBreak":0,"paused":0,"total":1}}');

        $presence = $this->worker()->teamPresence();

        self::assertSame('/v1/portal/team/presence', $this->pathOf($this->lastRequest()));
        self::assertSame(1, $presence->counts->working);
        self::assertSame('Ada', $presence->workers[0]->label);
    }

    // ---- transport behaviour ----

    public function testAPortalActionIsNotReplayedAfterAServerError(): void
    {
        // Portal actions carry no idempotency key, so retrying an accept could double-accept.
        $this->enqueueJson(503, '{"error":{"code":"internal_error","message":"boom"}}');
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"accepted"}');

        try {
            $this->worker(1)->accept('task_8fk2');
            self::fail('expected the action to surface the server error');
        } catch (FivexerApiException $e) {
            self::assertSame(503, $e->statusCode);
        }

        self::assertCount(1, $this->history);
    }

    public function testATransientReadFailureIsRetried(): void
    {
        $this->enqueueJson(503, '{"error":{"code":"internal_error","message":"boom"}}');
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":[]}');

        self::assertSame('agent_1', $this->worker(1)->queue()->workerId);
        self::assertCount(2, $this->history);
    }

    public function testARateLimitedReadWaitsTheAdvertisedDelayBeforeRetrying(): void
    {
        // A 1ms Retry-After exercises the real sleep path without slowing the suite.
        $this->enqueueJson(429, '{"error":{"code":"rate_limited","message":"slow down"}}', [
            'retry-after' => '0.001',
        ]);
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":[]}');

        self::assertSame('agent_1', $this->worker(1)->queue()->workerId);
        self::assertCount(2, $this->history);
    }

    public function testAMalformedRetryAfterIsIgnoredAndTheReadStillRetries(): void
    {
        $this->enqueueJson(503, '{"error":{"code":"boom","message":"b"}}', ['retry-after' => 'soon']);
        $this->enqueueJson(200, '{"workerId":"agent_1","taskIds":[]}');

        self::assertSame('agent_1', $this->worker(1)->queue()->workerId);
    }

    public function testAReadIsRetriedOnlyUpToTheConfiguredLimit(): void
    {
        $this->enqueueJson(503, '{"error":{"code":"boom","message":"b"}}');
        $this->enqueueJson(503, '{"error":{"code":"boom","message":"b"}}');

        try {
            $this->worker(1)->queue();
            self::fail('expected the retries to be exhausted');
        } catch (FivexerApiException $e) {
            self::assertSame(503, $e->statusCode);
        }

        self::assertCount(2, $this->history);
    }

    public function testAnUnparseableErrorBodyStillRaisesAStructuredError(): void
    {
        $this->enqueueJson(500, '<html>gateway</html>');

        try {
            $this->worker()->queue();
            self::fail('expected a structured error');
        } catch (FivexerApiException $e) {
            self::assertSame('unknown_error', $e->apiCode);
            self::assertSame(500, $e->statusCode);
            self::assertSame('http 500', $e->getMessage());
        }
    }

    public function testAnErrorBodyWhoseErrorFieldIsNotAnObjectFallsBackToDefaults(): void
    {
        $this->enqueueJson(400, '{"error":"just a string"}');

        try {
            $this->worker()->queue();
            self::fail('expected a structured error');
        } catch (FivexerApiException $e) {
            self::assertSame('unknown_error', $e->apiCode);
        }
    }

    public function testAnErrorObjectMissingAMessageStillReportsTheStatus(): void
    {
        $this->enqueueJson(422, '{"error":{"code":"validation_failed"}}');

        try {
            $this->worker()->queue();
            self::fail('expected a structured error');
        } catch (FivexerApiException $e) {
            self::assertSame('validation_failed', $e->apiCode);
            self::assertSame('http 422', $e->getMessage());
        }
    }

    public function testASuccessfulReadWithAnEmptyBodyYieldsDefaults(): void
    {
        // A 200 with no body should not blow up mid-parse.
        $this->enqueueJson(200, '');

        self::assertSame('', $this->worker()->queue()->workerId);
    }
}
