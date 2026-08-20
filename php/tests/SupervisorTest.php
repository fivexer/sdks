<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\FivexerSupervisor;
use Fivexer\SDK\Model\AcceptSupervisorInvite;
use Fivexer\SDK\Model\PushSubscriptionInput;
use Fivexer\SDK\Model\UnparkTask;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * The supervisor plane: a third credential type that watches and unblocks work.
 *
 * The distinguishing property is that there is nothing to recover with. A worker session
 * refreshes; a supervisor session is redeemed from a single-use link and, once expired, can only
 * be replaced by a new link. These tests pin that the client adopts a session on accept, drops it
 * on logout, and never invents a recovery path in between.
 */
final class SupervisorTest extends ClientTestCase
{
    private const SESSION = '{"token":"sv_new","expiresAt":1756000000000,'
        . '"supervisor":{"id":"sup_1","label":"Dana","email":"dana@example.com","teamId":"team_1"},'
        . '"workspaceId":"ws_1"}';

    private function supervisor(int $maxRetries = 0): FivexerSupervisor
    {
        return new FivexerSupervisor('https://api.fivexer.test', 'sv_s3ss10n', $this->guzzle(), $maxRetries);
    }

    private function anonymous(int $maxRetries = 0): FivexerSupervisor
    {
        return new FivexerSupervisor('https://api.fivexer.test', null, $this->guzzle(), $maxRetries);
    }

    // ---- session ----

    public function testRedeemingALinkAdoptsTheSession(): void
    {
        // Redeeming is the only way in, so a client that failed to adopt here would leave every
        // later call unauthenticated.
        $this->enqueueJson(201, self::SESSION);

        $client = $this->anonymous();
        $session = $client->acceptInvite(new AcceptSupervisorInvite('link_tok'));

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor-auth/accept', $this->pathOf($request));
        self::assertSame(['token' => 'link_tok'], $this->requestBodyJson($request));
        self::assertSame('Dana', $session->supervisor->label);
        self::assertSame('sv_new', $client->getSessionToken());
    }

    public function testExpiryIsEpochMillisNotTheIsoStringTheWorkerPlaneSends(): void
    {
        // The two planes genuinely differ on the wire. Normalising here would hide that from
        // anyone comparing the two clients side by side.
        $this->enqueueJson(201, self::SESSION);

        $client = $this->anonymous();
        $session = $client->acceptInvite(new AcceptSupervisorInvite('link_tok'));

        self::assertSame(1756000000000, $session->expiresAt);
        self::assertSame(1756000000000, $client->getSessionExpiresAt());
    }

    public function testASpentLinkIsOneIndistinct400(): void
    {
        // Expired, already-used, revoked and never-existed answer identically — the server
        // refuses to tell a grinder which half of a guess was right.
        $this->enqueueJson(400, '{"error":{"code":"invalid_token","message":"ask for a new one"}}');

        $client = $this->anonymous();
        try {
            $client->acceptInvite(new AcceptSupervisorInvite('spent'));
            self::fail('expected the refusal to propagate');
        } catch (FivexerApiException $e) {
            self::assertSame('invalid_token', $e->apiCode);
        }
        self::assertNull($client->getSessionToken());
    }

    public function testTheEntryPointIsUnauthenticated(): void
    {
        $this->enqueueJson(200, '{"consoleUrl":"https://console.5xer.com"}');

        self::assertSame('https://console.5xer.com', $this->anonymous()->entry()->consoleUrl);

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor-auth/entry', $this->pathOf($request));
        self::assertFalse($request->hasHeader('Authorization'));
    }

    public function testADeploymentWithNoDashboardOriginReportsANullConsoleUrl(): void
    {
        $this->enqueueJson(200, '{"consoleUrl":null}');

        self::assertNull($this->anonymous()->entry()->consoleUrl);
    }

    public function testAuthenticatedCallsCarryTheBearerToken(): void
    {
        $this->enqueueJson(200, '{"supervisorId":"sup_1","teamKey":"billing"}');

        $this->supervisor()->me();

        self::assertSame('Bearer sv_s3ss10n', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testLoggingOutForgetsTheToken(): void
    {
        $this->enqueueEmpty(204);

        $client = $this->supervisor();
        $client->logout();

        self::assertSame('/v1/supervisor-auth/logout', $this->pathOf($this->lastRequest()));
        self::assertNull($client->getSessionToken());
        self::assertNull($client->getSessionExpiresAt());
    }

    public function testATokenCanBeAdoptedAfterConstruction(): void
    {
        $this->enqueueJson(200, '{"consoleUrl":null}');

        $client = $this->anonymous();
        $client->setToken('sv_later', 1756000000000);
        $client->entry();

        self::assertSame('Bearer sv_later', $this->lastRequest()->getHeaderLine('Authorization'));
        self::assertSame(1756000000000, $client->getSessionExpiresAt());
    }

    public function testABaseUrlIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FivexerSupervisor('');
    }

    public function testATrailingSlashOnTheBaseUrlIsStripped(): void
    {
        // Not "//v1/..." — a doubled slash is a 404 on most gateways.
        $this->enqueueJson(200, '{"consoleUrl":null}');

        (new FivexerSupervisor('https://api.fivexer.test/', null, $this->guzzle(), 0))->entry();

        self::assertSame('/v1/supervisor-auth/entry', $this->pathOf($this->lastRequest()));
    }

    // ---- board ----

    public function testANullTeamKeyMeansTheWholeWorkspaceNotAMissingValue(): void
    {
        $this->enqueueJson(200, '{"supervisorId":"sup_1","label":"Dana","email":null,'
            . '"workspaceId":"ws_1","teamKey":null}');

        $me = $this->supervisor()->me();

        self::assertNull($me->teamKey);
        self::assertSame('ws_1', $me->workspaceId);
    }

    public function testTheWholeBoardArrivesInOneRequest(): void
    {
        // Deliberately one endpoint rather than four: this is a phone on a depot floor.
        $this->enqueueJson(200, '{"teamKey":"billing","counts":{"queued":4,"pending":2,'
            . '"parked":1,"oldestWaitMs":90000},"crew":['
            . '{"workerId":"agent_1","backlog":3,"available":true},'
            . '{"workerId":"agent_2","backlog":0,"available":false}],'
            . '"parked":[{"id":"task_8fk2","tags":["billing"],"priority":90,'
            . '"createdAt":1754000000000}]}');

        $board = $this->supervisor()->overview();

        self::assertCount(1, $this->history);
        self::assertSame('/v1/supervisor/overview', $this->pathOf($this->lastRequest()));
        self::assertSame(90000, $board->counts->oldestWaitMs);
        // Busiest first, so the board's top row is the one needing attention.
        self::assertSame(3, $board->crew[0]->backlog);
        self::assertSame('task_8fk2', $board->parked[0]->id);
    }

    public function testAnEmptyBoardParsesWithoutInventingDefaults(): void
    {
        $this->enqueueJson(200, '{"teamKey":null}');

        $board = $this->supervisor()->overview();

        self::assertSame(0, $board->counts->queued);
        self::assertSame([], $board->crew);
        self::assertSame([], $board->parked);
    }

    public function testAParkedTaskWithNoPriorityParsesAsNull(): void
    {
        $this->enqueueJson(200, '{"parked":[{"id":"t1","tags":[],"priority":null,"createdAt":null}]}');

        $parked = $this->supervisor()->overview()->parked[0];

        self::assertNull($parked->priority);
        self::assertNull($parked->createdAt);
    }

    // ---- actions ----

    public function testUnparkingWithNoOptionsSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"id":"t1","status":"queued"}');

        $this->supervisor()->unpark('t1');

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor/tasks/t1/unpark', $this->pathOf($request));
        // `{}`, not `[]` — a Fastify body schema rejects an array where it wants an object.
        self::assertSame('{}', $this->requestBody($request));
    }

    public function testUnparkResetFlagsReachTheWire(): void
    {
        $this->enqueueJson(200, '{"id":"t1","status":"queued"}');

        $this->supervisor()->unpark('t1', (new UnparkTask())->resetEscalation(true)->resetSla(true));

        self::assertSame(
            ['resetEscalation' => true, 'resetSla' => true],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testReprioritisingATask(): void
    {
        $this->enqueueJson(200, '{"id":"t1","priority":99}');

        self::assertSame(99.0, $this->supervisor()->setPriority('t1', 99)->priority);

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor/tasks/t1/priority', $this->pathOf($request));
        self::assertSame(['priority' => 99], $this->requestBodyJson($request));
    }

    public function testHandingATaskToACrewMember(): void
    {
        $this->enqueueJson(200, '{"id":"t1","workerId":"agent_1","status":"pending"}');

        self::assertSame('agent_1', $this->supervisor()->assign('t1', 'agent_1', true)->workerId);

        self::assertSame(
            ['workerId' => 'agent_1', 'force' => true],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testAnUnsetForceFlagIsAbsentFromTheAssignBody(): void
    {
        $this->enqueueJson(200, '{"id":"t1","workerId":"agent_1","status":"pending"}');

        $this->supervisor()->assign('t1', 'agent_1');

        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($this->lastRequest()));
    }

    public function testARefusedAssignmentReachesTheCallerAsAssignBlocked(): void
    {
        // The matcher throws on a refusal; the API turns that into a 400 an operator can read.
        $this->enqueueJson(400, '{"error":{"code":"assign_blocked","message":"worker is paused"}}');

        try {
            $this->supervisor()->assign('t1', 'agent_1');
            self::fail('expected the refusal to propagate');
        } catch (FivexerApiException $e) {
            self::assertSame('assign_blocked', $e->apiCode);
            self::assertSame(400, $e->statusCode);
        }
    }

    public function testATaskOutsideThisCrewIsRefusedNotSilentlyMoved(): void
    {
        $this->enqueueJson(403, '{"error":{"code":"out_of_scope","message":"not your crew"}}');

        try {
            $this->supervisor()->unpark('someone_elses');
            self::fail('expected a scope refusal');
        } catch (FivexerApiException $e) {
            self::assertSame(403, $e->statusCode);
        }
    }

    public function testPausingACrewMemberHoldsTheirBacklogByDefault(): void
    {
        // Pausing never releases implicitly — that is the engine's rule, and the empty list is
        // how the caller sees the backlog held.
        $this->enqueueJson(200, '{"workerId":"agent_1","available":false,"releasedTaskIds":[]}');

        $result = $this->supervisor()->setAvailability('agent_1', false);

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor/workers/agent_1/availability', $this->pathOf($request));
        self::assertSame(['available' => false], $this->requestBodyJson($request));
        self::assertSame([], $result->releasedTaskIds);
    }

    public function testReleasingTheBacklogNamesWhatMoved(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","available":false,"releasedTaskIds":["t1","t2"]}');

        $result = $this->supervisor()->setAvailability('agent_1', false, true);

        self::assertSame(
            ['available' => false, 'releaseBacklog' => true],
            $this->requestBodyJson($this->lastRequest())
        );
        self::assertSame(['t1', 't2'], $result->releasedTaskIds);
    }

    public function testIdsCannotEscapeTheirPathSegment(): void
    {
        $this->enqueueJson(200, '{"id":"x","status":"queued"}');

        $this->supervisor()->unpark('a/../b');

        self::assertSame('/v1/supervisor/tasks/a%2F..%2Fb/unpark', $this->pathOf($this->lastRequest()));
    }

    // ---- push ----

    public function testPushConfigReportsADeploymentWithoutVapidKeysAsDisabled(): void
    {
        $this->enqueueJson(200, '{"enabled":false,"publicKey":null}');

        $config = $this->supervisor()->pushConfig();

        self::assertSame('/v1/supervisor/push/config', $this->pathOf($this->lastRequest()));
        self::assertFalse($config->enabled);
        self::assertNull($config->publicKey);
    }

    public function testSubscribingNestsTheBrowserKeysAndReturnsTheBareAck(): void
    {
        // Unlike the worker plane this returns only an ack — the supervisor table is keyed by
        // endpoint and has nothing else to hand back.
        $this->enqueueJson(201, '{"ok":true}');

        $accepted = $this->supervisor()->pushSubscribe(
            new PushSubscriptionInput('https://fcm/x', 'p2', 'au')
        );

        self::assertSame(
            ['endpoint' => 'https://fcm/x', 'keys' => ['p256dh' => 'p2', 'auth' => 'au']],
            $this->requestBodyJson($this->lastRequest())
        );
        self::assertTrue($accepted);
    }

    public function testUnsubscribingSendsOnlyTheEndpoint(): void
    {
        $this->enqueueEmpty(204);

        $this->supervisor()->pushUnsubscribe('https://fcm/x');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(['endpoint' => 'https://fcm/x'], $this->requestBodyJson($request));
    }

    // ---- errors and retry ----

    public function testAnExpiredSessionIsA401WithNoRecoveryAttempted(): void
    {
        // There is no refresh endpoint on this plane: a dead session means "get a new link".
        $this->enqueueJson(401, '{"error":{"code":"unauthorized","message":"expired"}}');

        try {
            $this->supervisor()->me();
            self::fail('expected a 401');
        } catch (FivexerApiException $e) {
            self::assertSame(401, $e->statusCode);
        }
        self::assertCount(1, $this->history);
    }

    public function testANonJsonErrorBodyStillNamesTheStatus(): void
    {
        // A proxy's HTML error page must not turn into a parse failure that hides the 502.
        $this->enqueueJson(502, '<html>bad gateway</html>');

        try {
            $this->supervisor()->overview();
            self::fail('expected a 502');
        } catch (FivexerApiException $e) {
            self::assertSame(502, $e->statusCode);
            self::assertSame('unknown_error', $e->apiCode);
        }
    }

    public function testAReadIsRetriedOnceOnA429(): void
    {
        $this->mock->append(
            new \GuzzleHttp\Psr7\Response(429, ['retry-after' => '0'], '{"error":{"code":"rate_limited","message":"slow"}}'),
            new \GuzzleHttp\Psr7\Response(200, [], '{"consoleUrl":null}')
        );

        (new FivexerSupervisor('https://api.fivexer.test', null, $this->guzzle(), 1))->entry();

        self::assertCount(2, $this->history);
    }

    public function testAnActionIsNeverRetriedBecauseItCarriesNoIdempotencyKey(): void
    {
        // A replayed assign could move a task twice, so only reads are retried.
        $this->enqueueJson(429, '{"error":{"code":"rate_limited","message":"slow"}}', ['retry-after' => '0']);

        try {
            $this->supervisor(1)->assign('t1', 'agent_1');
            self::fail('expected the 429 to surface');
        } catch (FivexerApiException $e) {
            self::assertSame(429, $e->statusCode);
        }
        self::assertCount(1, $this->history);
    }
}
