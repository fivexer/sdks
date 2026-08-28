<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\UpsertWorker;
use Fivexer\SDK\Model\WorkerLogin;

/**
 * Worker records: learned weights, team membership, and the two "not simply off shift" states.
 *
 * The routing-weight fields exist as three separate maps because they answer three separate
 * questions: what is in force, which entries the learning layer owns, and what to restore on a
 * revert. Collapsing them would make "an operator vetoed this tag" indistinguishable from "the
 * model did" — the difference between a decision to keep and one to undo.
 */
final class WorkerRoutingAndTeamsTest extends ClientTestCase
{
    private const DETAIL = '{"id":"agent_1","tags":["english","billing"],'
        . '"routingWeights":{"english":1.0,"billing":0.0},'
        . '"learnedRoutingWeights":{"billing":0.0},'
        . '"routingWeightsSnapshot":{"english":1.0,"billing":1.0},'
        . '"learnedRoutingWeightsSyncedAt":1756000000000,"skills":null,'
        . '"teams":[{"teamId":"team_1","key":"support","name":"Support","color":"#4488ff","role":"lead"},'
        . '{"teamId":"team_2","key":"ops","name":"Ops","color":null,"role":"member"}],'
        . '"maxBacklogSize":5,"available":false,"invitePending":true,"pendingApproval":false,'
        . '"queueDepth":2}';

    public function testWorkerDetailSeparatesLearnedWeightsFromTheOnesInForce(): void
    {
        $this->enqueueJson(200, self::DETAIL);

        $worker = $this->client()->workers()->get('agent_1');

        self::assertSame(['english' => 1.0, 'billing' => 0.0], $worker->routingWeights);
        // The veto on `billing` came from the model, not an operator — that is what makes it
        // revertible, and it is only knowable because the two maps stay separate.
        self::assertSame(['billing' => 0.0], $worker->learnedRoutingWeights);
        self::assertSame(['english' => 1.0, 'billing' => 1.0], $worker->routingWeightsSnapshot);
        self::assertSame(1756000000000, $worker->learnedRoutingWeightsSyncedAt);
    }

    public function testWorkerDetailReadsTeamMembershipWithRoles(): void
    {
        $this->enqueueJson(200, self::DETAIL);

        $worker = $this->client()->workers()->get('agent_1');

        self::assertNotNull($worker->teams);
        self::assertCount(2, $worker->teams);
        self::assertSame('team_1', $worker->teams[0]->teamId);
        self::assertSame('lead', $worker->teams[0]->role);
        self::assertSame('Support', $worker->teams[0]->name);
        self::assertNull($worker->teams[1]->color);
    }

    public function testUnavailableIsDistinguishedFromInvitedAndAwaitingApproval(): void
    {
        $this->enqueueJson(200, self::DETAIL);

        $worker = $this->client()->workers()->get('agent_1');

        // An operator UI that renders all three as "paused" tells the wrong story about all
        // three: nobody has to resume an invite, they have to chase it.
        self::assertFalse($worker->available);
        self::assertTrue($worker->invitePending);
        self::assertFalse($worker->pendingApproval);
    }

    public function testAWorkerWithNoTeamsReadsAsNullRatherThanAnEmptyList(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1","tags":[],"available":true,"queueDepth":0}');

        $worker = $this->client()->workers()->get('agent_1');

        // Null means "the server said nothing"; an empty list would claim membership of none.
        self::assertNull($worker->teams);
        self::assertNull($worker->learnedRoutingWeights);
        self::assertFalse($worker->invitePending);
    }

    public function testQueueStatsCarryTheSameTwoStatesPerWorker(): void
    {
        $this->enqueueJson(200, '{"plan":"growth","tasks":{"queued":3},"workers":1,'
            . '"queue":{"oldestWaitingMs":4200,"perWorker":[{"workerId":"agent_1","backlog":2,'
            . '"maxBacklogSize":5,"available":false,"invitePending":false,"pendingApproval":true}]}}');

        $stats = $this->client()->stats();

        self::assertFalse($stats->queue->perWorker[0]->available);
        self::assertTrue($stats->queue->perWorker[0]->pendingApproval);
        self::assertFalse($stats->queue->perWorker[0]->invitePending);
    }

    public function testUpsertCanSetTeamMembershipAndOpenShiftState(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert(
            (new UpsertWorker('agent_1'))->tags(['english'])->teamIds(['team_1', 'team_2'])->available(true)
        );

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame(['team_1', 'team_2'], $body['teamIds']);
        // The escape hatch for programmatic fleets with no human at a portal: without it a newly
        // created agent worker is off shift and never matched.
        self::assertTrue($body['available']);
    }

    public function testAnEmptyTeamListClearsMembershipAndIsNotPruned(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert((new UpsertWorker('agent_1'))->teamIds([]));

        // An empty list is a wholesale replacement with nothing — the only way to remove a
        // worker from every team. Pruning it would silently turn a clear into a no-op.
        $body = $this->requestBodyJson($this->lastRequest());
        self::assertArrayHasKey('teamIds', $body);
        self::assertSame([], $body['teamIds']);
    }

    public function testOmittingTeamIdsAndAvailabilityLeavesBothUntouched(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert((new UpsertWorker('agent_1'))->tags(['english']));

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertArrayNotHasKey('teamIds', $body);
        // Sending available=false here would clock a working person off shift on an unrelated
        // update, which is why the property is nullable rather than defaulted to false.
        self::assertArrayNotHasKey('available', $body);
    }

    public function testAWorkerCanBeCreatedExplicitlyOffShift(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $this->client()->workers()->upsert((new UpsertWorker('agent_1'))->available(false));

        self::assertFalse($this->requestBodyJson($this->lastRequest())['available']);
    }

    // ---- the session's own expiry ----

    public function testLoggingInReportsWhenTheSessionExpires(): void
    {
        $this->enqueueJson(200, '{"token":"wt_new","expiresAt":"2026-09-01T00:00:00.000Z"}');

        $session = $this->anonymousWorker()->login(new WorkerLogin('ws_1', 'agent_1', '4821'));

        // Rotating ahead of expiry needs the expiry. Without it a caller can only rotate on a
        // 401, which puts a failed request in front of a person at the start of every session.
        self::assertSame('2026-09-01T00:00:00.000Z', $session->expiresAt);
    }

    public function testAServerPredatingTheRefreshEndpointReportsNoExpiry(): void
    {
        $this->enqueueJson(200, '{"token":"wt_new"}');

        $session = $this->anonymousWorker()->login(new WorkerLogin('ws_1', 'agent_1', '4821'));

        // Null, not an invented timestamp: a made-up expiry rotates far too early, or never.
        self::assertNull($session->expiresAt);
    }

    // ---- learned-weight options ----

    public function testPreviewingWeightsCarriesTheTwoWideningFlags(): void
    {
        $this->enqueueJson(200, '{"workers":[]}');

        $this->client()->learning()->previewWeights('agent_1', true, true);

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('workerId=agent_1', $query);
        self::assertStringContainsString('overrideManual=true', $query);
        self::assertStringContainsString('includeUnexploredTags=true', $query);
    }

    public function testPreviewingOmitsFlagsThatWereNotAskedFor(): void
    {
        $this->enqueueJson(200, '{"workers":[]}');

        $this->client()->learning()->previewWeights('agent_1');

        // Both default off server-side. Echoing false would make "I did not ask" look like a
        // deliberate refusal, and the preview would stop matching what a bare apply() writes.
        self::assertSame('workerId=agent_1', $this->queryOf($this->lastRequest()));
    }

    public function testPreviewingCanSendAFlagOffExplicitly(): void
    {
        $this->enqueueJson(200, '{"workers":[]}');

        $this->client()->learning()->previewWeights(null, false);

        // An explicit false is a real instruction — "synthesize, but do not touch what an
        // operator set by hand" — and must reach the wire rather than being pruned as a default.
        self::assertSame('overrideManual=false', $this->queryOf($this->lastRequest()));
    }

    public function testApplyingWeightsSendsTheFlagsInTheBody(): void
    {
        $this->enqueueJson(200, '{"applied":{"agent_1":{"billing":0.0}}}');

        $applied = $this->client()->learning()->applyWeights(['agent_1'], true, false);

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertTrue($body['overrideManual']);
        self::assertFalse($body['includeUnexploredTags']);
        self::assertSame(['agent_1' => ['billing' => 0.0]], $applied);
    }

    public function testApplyingWithoutFlagsSendsOnlyTheWorkerIds(): void
    {
        $this->enqueueJson(200, '{"applied":{}}');

        $this->client()->learning()->applyWeights(['agent_1']);

        self::assertSame(['workerIds' => ['agent_1']], $this->requestBodyJson($this->lastRequest()));
    }
}
