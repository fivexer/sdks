<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\EscalationPolicy;
use Fivexer\SDK\Model\RecurrencePolicy;
use Fivexer\SDK\Model\RequiredSkill;
use Fivexer\SDK\Model\SchedulePolicy;
use Fivexer\SDK\Model\SlaPolicy;

/**
 * Task policies on the way in and on the way out.
 *
 * Four policies reach tasks()->create(): escalation (the response clock), SLA (the completion
 * clock, shelf life and rejection budget), schedule (when a task may be offered at all) and
 * recurrence (a standing template). They are separate objects because they answer separate
 * questions and are stored separately.
 *
 * The distinction these tests protect is *absent* versus *null*. Omitting a policy inherits the
 * workspace default; sending an explicit null opts the task out of it. Json::compact() drops
 * nulls, which is right for every other field and wrong for exactly these two — hence
 * escalationNone() / slaNone().
 */
final class TaskPoliciesTest extends ClientTestCase
{
    public function testEveryPolicyIsSentAsItsOwnWireObject(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create(
            (new CreateTask(['billing']))
                ->escalation(
                    (new EscalationPolicy(60000))
                        ->onNoResponse('block')
                        ->priorityBoost(10)
                        ->tiers([['billing'], ['billing', 'english']])
                        ->maxEscalations(2)
                        ->onExhausted('park')
                )
                ->sla(
                    (new SlaPolicy())
                        ->completeWithinMs(3600000)
                        ->expireAfterMs(86400000)
                        ->maxRejections(3)
                        ->onCompletionBreach('notify')
                        ->onMaxRejections('park')
                        ->onExpire('drop')
                )
                ->schedule((new SchedulePolicy())->notBefore(1756000000000)->notAfter(1756003600000)->onMiss('park'))
        );

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame([
            'onNoResponse' => 'block',
            'priorityBoost' => 10.0,
            'tiers' => [['billing'], ['billing', 'english']],
            'maxEscalations' => 2,
            'onExhausted' => 'park',
            'respondWithinMs' => 60000,
        ], $body['escalation']);
        self::assertSame([
            'completeWithinMs' => 3600000,
            'expireAfterMs' => 86400000,
            'maxRejections' => 3,
            'onCompletionBreach' => 'notify',
            'onMaxRejections' => 'park',
            'onExpire' => 'drop',
        ], $body['sla']);
        self::assertSame(
            ['notBefore' => 1756000000000, 'notAfter' => 1756003600000, 'onMiss' => 'park'],
            $body['schedule']
        );
    }

    public function testUnsetFieldsInsideAPolicyAreOmittedRatherThanNulled(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create((new CreateTask(['billing']))->escalation(new EscalationPolicy(30000)));

        // A null inside a policy object reads as "clear this setting", not "I did not set it".
        self::assertSame(
            ['respondWithinMs' => 30000],
            $this->requestBodyJson($this->lastRequest())['escalation']
        );
    }

    public function testOmittingAPolicyLeavesItOffTheBodyEntirely(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create(new CreateTask(['billing']));

        // Absent means "inherit the workspace default" — not the SDK's decision to make.
        $body = $this->requestBodyJson($this->lastRequest());
        self::assertArrayNotHasKey('escalation', $body);
        self::assertArrayNotHasKey('sla', $body);
    }

    public function testOptingOutOfAWorkspaceDefaultSendsAnExplicitNull(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create((new CreateTask(['billing']))->escalationNone()->slaNone());

        // The one case where a null must survive the omit-nulls pass.
        $body = $this->requestBodyJson($this->lastRequest());
        self::assertArrayHasKey('escalation', $body);
        self::assertNull($body['escalation']);
        self::assertArrayHasKey('sla', $body);
        self::assertNull($body['sla']);
    }

    public function testOptingOnlyTheSlaOutLeavesEscalationAbsent(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create((new CreateTask(['billing']))->slaNone());

        // The two opt-outs are independent: nulling the SLA must not drag escalation onto the
        // wire as a null too, which would silently opt the task out of a default it wanted.
        $body = $this->requestBodyJson($this->lastRequest());
        self::assertNull($body['sla']);
        self::assertArrayNotHasKey('escalation', $body);
    }

    public function testSettingAPolicyAfterOptingOutRestoresIt(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        // Builders are mutable and reusable; an opt-out must not be a one-way door.
        $this->client()->tasks()->create(
            (new CreateTask(['billing']))->escalationNone()->escalation(new EscalationPolicy(1000))
        );

        self::assertSame(
            ['respondWithinMs' => 1000],
            $this->requestBodyJson($this->lastRequest())['escalation']
        );
    }

    public function testRequiredSkillsAndTheTwoTeamFieldsReachTheBody(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create(
            (new CreateTask(['billing']))
                ->requiredSkills([new RequiredSkill('sk_node', 3)])
                ->teamId('team_1')
        );

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame([['skillId' => 'sk_node', 'minLevel' => 3]], $body['requiredSkills']);
        self::assertSame('team_1', $body['teamId']);
    }

    public function testASoftTeamPreferenceIsNeverSentAsTheHardGate(): void
    {
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client()->tasks()->create((new CreateTask(['billing']))->preferTeamId('team_1'));

        // teamId excludes everyone else; preferTeamId only reorders. Confusing them changes who
        // can take the task, not just who takes it first.
        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame('team_1', $body['preferTeamId']);
        self::assertArrayNotHasKey('teamId', $body);
    }

    public function testARecurrenceCarriesItsWholeClock(): void
    {
        $this->enqueueJson(202, '{"id":"nightly","status":"recurring"}');

        $this->client()->tasks()->create(
            (new CreateTask(['ops']))
                ->id('nightly')
                ->recurrence(
                    (new RecurrencePolicy(86400000))
                        ->startAt(1756000000000)
                        ->windowMs(3600000)
                        ->onMiss('park')
                        ->until(1788000000000)
                        ->maxOccurrences(30)
                        ->catchUp('all')
                )
        );

        self::assertSame([
            'startAt' => 1756000000000,
            'windowMs' => 3600000,
            'onMiss' => 'park',
            'until' => 1788000000000,
            'maxOccurrences' => 30,
            'catchUp' => 'all',
            'everyMs' => 86400000,
        ], $this->requestBodyJson($this->lastRequest())['recurrence']);
    }

    public function testAMinimalRecurrenceSendsOnlyTheInterval(): void
    {
        $this->enqueueJson(202, '{"id":"nightly","status":"recurring"}');

        $this->client()->tasks()->create((new CreateTask(['ops']))->recurrence(new RecurrencePolicy(60000)));

        // catchUp defaults to 'skip' server-side; echoing it would claim a choice nobody made.
        self::assertSame(
            ['everyMs' => 60000],
            $this->requestBodyJson($this->lastRequest())['recurrence']
        );
    }

    public function testATaskReadsBackItsPoliciesAndItsLadderPosition(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","tags":["billing"],"priority":90,"status":"queued",'
            . '"workerId":null,"createdAt":1756000000000,"skillThresholds":{"billing":3},'
            . '"escalation":{"respondWithinMs":60000,"onNoResponse":"block"},"escalationLevel":2,'
            . '"sla":{"completeWithinMs":3600000},"schedule":{"notBefore":1756000000000}}');

        $task = $this->client()->tasks()->get('task_1');

        self::assertNotNull($task->escalation);
        self::assertSame(60000, $task->escalation->respondWithinMs);
        // Without escalationLevel a client can render the ladder but not where the task sits.
        self::assertSame(2, $task->escalationLevel);
        self::assertNotNull($task->sla);
        self::assertSame(3600000, $task->sla->getCompleteWithinMs());
        self::assertNotNull($task->schedule);
        self::assertSame(1756000000000, $task->schedule->getNotBefore());
        // The gate a queued task is waiting on — the answer to "why is this still queued?"
        self::assertSame(['billing' => 3.0], $task->skillThresholds);
    }

    public function testATaskWithoutPoliciesReadsThemAsNullNotAsEmptyObjects(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","tags":[],"priority":null,"status":"queued",'
            . '"workerId":null,"createdAt":1,"escalation":null,"sla":null}');

        $task = $this->client()->tasks()->get('task_1');

        // An empty SlaPolicy would read as "an SLA with no deadlines", a different fact.
        self::assertNull($task->escalation);
        self::assertNull($task->sla);
        self::assertNull($task->escalationLevel);
        self::assertNull($task->skillThresholds);
    }
}
