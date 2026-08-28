<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Model\AcceptWorkerInvite;
use Fivexer\SDK\Model\ChangePin;
use Fivexer\SDK\Model\JoinWorkspace;
use Fivexer\SDK\Model\PushSubscriptionInput;
use Fivexer\SDK\Model\WorkerDeviceInput;
use Fivexer\SDK\Model\WorkerLocation;
use Fivexer\SDK\Model\WorkerSkillLevel;

/**
 * The worker's own view: signing in without a password, going on shift, and push.
 *
 * This plane has one hazard the workspace plane does not: three calls (join, acceptInvite,
 * refresh) *mint* a session, so each must adopt the returned token or the very next call goes out
 * unauthenticated. Those adoptions are asserted individually below.
 */
final class WorkerSelfServiceTest extends ClientTestCase
{
    // ---- sessions that mint a token ----

    public function testJoiningByQrAdoptsTheReturnedSession(): void
    {
        // The worker id is server-generated here, so adopting it is what makes every later call
        // (which defaults its worker id) work at all.
        $this->enqueueJson(201, '{"token":"wt_new","expiresAt":"2026-09-01T00:00:00Z",'
            . '"workspaceId":"ws_1","workerId":"agent_9","label":"Ada","pendingApproval":true,'
            . '"portalUrl":"https://5xer.com/portal/ws_1/"}');

        $worker = $this->anonymousWorker();
        $result = $worker->join(new JoinWorkspace('jt', 'Ada', '4821'));

        $request = $this->lastRequest();
        self::assertSame('/v1/worker-auth/join', $this->pathOf($request));
        self::assertSame(
            ['token' => 'jt', 'name' => 'Ada', 'pin' => '4821'],
            $this->requestBodyJson($request)
        );
        self::assertTrue($result->pendingApproval);
        self::assertSame('wt_new', $worker->getSessionToken());
        self::assertSame('agent_9', $worker->getWorkerId());
    }

    public function testAJoinCarryingAnEmailIncludesIt(): void
    {
        $this->enqueueJson(201, '{"token":"wt_new","workspaceId":"ws_1","workerId":"agent_9"}');

        $this->anonymousWorker()->join(
            (new JoinWorkspace('jt', 'Ada', '4821'))->email('ada@example.com')
        );

        self::assertSame('ada@example.com', $this->requestBodyJson($this->lastRequest())['email']);
    }

    public function testAcceptingAnEmailedInviteSignsTheWorkerIn(): void
    {
        // Setting a PIN *is* the sign-in — otherwise the worker's next act after choosing one
        // would be to retype it into a login form, which is what the magic link exists to avoid.
        $this->enqueueJson(200, '{"token":"wt_invited","workerId":"agent_1","workspaceId":"ws_1",'
            . '"label":"Ada","portalUrl":"https://x/"}');

        $worker = $this->anonymousWorker();
        $result = $worker->acceptInvite(new AcceptWorkerInvite('it', '4821'));

        $request = $this->lastRequest();
        self::assertSame('/v1/worker-auth/accept-invite', $this->pathOf($request));
        self::assertSame(['token' => 'it', 'pin' => '4821'], $this->requestBodyJson($request));
        self::assertSame('agent_1', $result->workerId);
        self::assertSame('wt_invited', $worker->getSessionToken());
    }

    public function testRefreshRotatesTheTokenInPlace(): void
    {
        $this->enqueueJson(200, '{"token":"wt_rotated","expiresAt":"2026-09-01T00:00:00Z"}');

        $worker = $this->worker();

        self::assertTrue($worker->refresh());
        self::assertSame('/v1/worker-auth/refresh', $this->pathOf($this->lastRequest()));
        self::assertSame('wt_rotated', $worker->getSessionToken());
    }

    public function testRefreshWithoutATokenShortCircuitsWithoutARequest(): void
    {
        self::assertFalse($this->anonymousWorker()->refresh());
        self::assertSame([], $this->history);
    }

    public function testA4xxOnRefreshMeansReauthenticateNotCrash(): void
    {
        // The caller's job here is to show a login prompt, not to handle an exception.
        $this->enqueueJson(401, '{"error":{"code":"unauthorized","message":"expired"}}');

        $worker = $this->worker();

        self::assertFalse($worker->refresh());
        // The dead token is left in place; only a successful rotation replaces it.
        self::assertSame('wt_s3ss10n', $worker->getSessionToken());
    }

    public function testA5xxOnRefreshIsRaisedSoTheCurrentTokenStaysUsable(): void
    {
        // A transient server fault must not be mistaken for "your session ended".
        $this->enqueueJson(503, '{"error":{"code":"unavailable","message":"down"}}');

        $worker = $this->worker();

        try {
            $worker->refresh();
            self::fail('expected the 5xx to propagate');
        } catch (FivexerApiException $e) {
            self::assertSame(503, $e->statusCode);
        }
        self::assertSame('wt_s3ss10n', $worker->getSessionToken());
    }

    // ---- shift and identity ----

    public function testMeReportsShiftStateAndWhetherSkillsStillNeedSetting(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","label":"Ada","available":false,'
            . '"pendingApproval":false,"onBreak":false,"breakStartedAt":null,'
            . '"skills":[{"skillId":"sk_1","key":"welsh","name":"Welsh","level":3}],'
            . '"skillSetupPending":true}');

        $me = $this->worker()->me();

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/me', $this->pathOf($request));
        self::assertSame('Bearer wt_s3ss10n', $request->getHeaderLine('Authorization'));
        self::assertTrue($me->skillSetupPending);
        self::assertSame('welsh', $me->skills[0]->key);
    }

    public function testGoingOnShiftIsTheWorkersOwnSwitch(): void
    {
        // Workers are created off shift, so this call is what makes someone matchable at all.
        $this->enqueueJson(200, '{"workerId":"agent_1","available":true}');

        self::assertTrue($this->worker()->setAvailability(true)->available);

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/me/availability', $this->pathOf($request));
        // No liveness contract unless one is asked for: an interactive client must not send it.
        self::assertSame(['available' => true], $this->requestBodyJson($request));
    }

    public function testAnUnattendedWorkerCanDeclareHowLongItsSilenceMayLast(): void
    {
        // The opt-in that lets the platform clock out a crashed daemon instead of leaving it
        // "available" forever. Only a headless client should ever send it.
        $this->enqueueJson(200, '{"workerId":"agent_1","available":true}');

        $this->worker()->setAvailability(true, 900000);

        self::assertSame(
            ['available' => true, 'staleAfterMs' => 900000],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testALivenessBudgetIsNeverSentWhenGoingOffShift(): void
    {
        // It only means anything alongside `available: true` — a paused worker is not silent,
        // it is off shift, and there is nothing left to clock out.
        $this->enqueueJson(200, '{"workerId":"agent_1","available":false}');

        $this->worker()->setAvailability(false, 900000);

        self::assertSame(['available' => false], $this->requestBodyJson($this->lastRequest()));
    }

    public function testChangingAPinReturnsNothingAndKeepsTheSession(): void
    {
        $this->enqueueEmpty(204);

        $worker = $this->worker();
        $worker->changePin(new ChangePin('1111', '4821'));

        self::assertSame(
            ['currentPin' => '1111', 'newPin' => '4821'],
            $this->requestBodyJson($this->lastRequest())
        );
        self::assertSame('wt_s3ss10n', $worker->getSessionToken());
    }

    public function testTheSkillCatalogIsReadOnlyAndUnwrapped(): void
    {
        // Inventing a skill is an operator's decision; a worker picks from this list or none.
        $this->enqueueJson(200, '{"skills":[{"id":"sk_1","key":"welsh","name":"Welsh","createdAt":"x"}]}');

        self::assertSame('welsh', $this->worker()->skillCatalog()[0]->key);
        self::assertSame('/v1/portal/skills', $this->pathOf($this->lastRequest()));
    }

    public function testAnAbsentCatalogArrayReadsAsEmpty(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->worker()->skillCatalog());
    }

    public function testSettingSkillsReplacesTheWholeSet(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","skills":[{"skillId":"sk_1",'
            . '"key":"welsh","name":"Welsh","level":4}]}');

        $result = $this->worker()->setSkills([new WorkerSkillLevel('sk_1', 4)]);

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/v1/portal/me/skills', $this->pathOf($request));
        self::assertSame(
            ['skills' => [['skillId' => 'sk_1', 'level' => 4]]],
            $this->requestBodyJson($request)
        );
        self::assertSame(4, $result->skills[0]->level);
    }

    // ---- comments, metrics, location ----

    public function testAWorkerReadsCommentsOnTheirOwnTask(): void
    {
        $this->enqueueJson(200, '{"comments":[{"id":"c1","body":"called back",'
            . '"createdAt":1754000000000}],"hasMore":false}');

        self::assertSame('called back', $this->worker()->comments('t1')->comments[0]->body);
        self::assertSame('/v1/portal/tasks/t1/comments', $this->pathOf($this->lastRequest()));
    }

    public function testWorkerCommentPagingParamsReachTheWire(): void
    {
        // The worker transport had no query support at all until this surface needed it; a
        // silently dropped cursor would page forever on the first page.
        $this->enqueueJson(200, '{"comments":[],"hasMore":false}');

        $this->worker()->comments('t1', 'c_42', 5);

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('cursor=c_42', $query);
        self::assertStringContainsString('limit=5', $query);
    }

    public function testAWorkerAddsACommentWithABareBodyField(): void
    {
        $this->enqueueJson(201, '{"comment":{"id":"c2","body":"on my way","createdAt":1}}');

        // The envelope is unwrapped: the caller asked for a comment, not a wrapper.
        self::assertSame('on my way', $this->worker()->addComment('t1', 'on my way')->body);
        self::assertSame(['body' => 'on my way'], $this->requestBodyJson($this->lastRequest()));
    }

    public function testTheMetricsWindowDefaultsToSevenDays(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"2026-08-13",'
            . '"days":[{"day":"2026-08-13","completed":4}],"medianWaitMs":1200,"medianCycleMs":45000}');

        $metrics = $this->worker()->metricsWindow();

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/metrics', $this->pathOf($request));
        self::assertSame('window=7d', $this->queryOf($request));
        self::assertSame(4, $metrics->days[0]->completed);
        self::assertSame(45000, $metrics->medianCycleMs);
    }

    public function testAnExplicitWindowOverridesTheDefaultAndNoHistoryReadsAsNull(): void
    {
        // No data yet is null, not zero — a fresh worker has no median, not a median of nothing.
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"x","days":[],'
            . '"medianWaitMs":null,"medianCycleMs":null}');

        $metrics = $this->worker()->metricsWindow('30d');

        self::assertSame('window=30d', $this->queryOf($this->lastRequest()));
        self::assertNull($metrics->medianWaitMs);
    }

    public function testUpdatingLocationSendsPlainCoordinates(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","latitude":59.4,"longitude":24.7}');

        self::assertSame(24.7, $this->worker()->updateLocation(new WorkerLocation(59.4, 24.7))->longitude);

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/workers/me/location', $this->pathOf($request));
        self::assertSame(['latitude' => 59.4, 'longitude' => 24.7], $this->requestBodyJson($request));
    }

    // ---- push: native devices and web push ----

    public function testRegisteringAnExpoDeviceDefaultsThePlatform(): void
    {
        $this->enqueueJson(201, '{"token":"ExpoTok","platform":"unknown"}');

        $device = $this->worker()->registerDevice(new WorkerDeviceInput('ExpoTok'));

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/devices', $this->pathOf($request));
        self::assertSame(
            ['token' => 'ExpoTok', 'platform' => 'unknown'],
            $this->requestBodyJson($request)
        );
        self::assertSame('unknown', $device->platform);
    }

    public function testUnregisteringADeviceSendsTheTokenInTheBodyOfADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->worker()->unregisterDevice('ExpoTok');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(['token' => 'ExpoTok'], $this->requestBodyJson($request));
    }

    public function testPushConfigReportsADeploymentWithoutVapidKeysAsDisabled(): void
    {
        // Not an error: the portal still works, it just cannot push. Prompting for notification
        // permission here would burn the one prompt a browser gives you.
        $this->enqueueJson(200, '{"enabled":false,"publicKey":null}');

        $config = $this->worker()->pushConfig();

        self::assertSame('/v1/portal/push/config', $this->pathOf($this->lastRequest()));
        self::assertFalse($config->enabled);
        self::assertNull($config->publicKey);
    }

    public function testPushConfigCarriesTheVapidKeyWhenEnabled(): void
    {
        $this->enqueueJson(200, '{"enabled":true,"publicKey":"BPk..."}');

        self::assertSame('BPk...', $this->worker()->pushConfig()->publicKey);
    }

    public function testSubscribingNestsTheBrowserKeysTheWayTheApiExpects(): void
    {
        // The browser hands over a flat-ish PushSubscription; the wire wants keys:{p256dh,auth}.
        $this->enqueueJson(201, '{"endpoint":"https://fcm/x","createdAt":"2026-08-20T00:00:00Z"}');

        $subscription = $this->worker()->pushSubscribe(
            new PushSubscriptionInput('https://fcm/x', 'p2', 'au')
        );

        $request = $this->lastRequest();
        self::assertSame('/v1/portal/push/subscriptions', $this->pathOf($request));
        self::assertSame(
            ['endpoint' => 'https://fcm/x', 'keys' => ['p256dh' => 'p2', 'auth' => 'au']],
            $this->requestBodyJson($request)
        );
        self::assertSame('https://fcm/x', $subscription->endpoint);
    }

    public function testUnsubscribingSendsOnlyTheEndpoint(): void
    {
        // By the time a browser fires `pushsubscriptionchange` it has already discarded the keys,
        // so requiring them here would make unsubscribe impossible in the case it exists for.
        $this->enqueueEmpty(204);

        $this->worker()->pushUnsubscribe('https://fcm/x');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(['endpoint' => 'https://fcm/x'], $this->requestBodyJson($request));
    }
}
