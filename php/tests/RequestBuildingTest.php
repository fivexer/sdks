<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\AddComment;
use Fivexer\SDK\Model\CreateAttachment;
use Fivexer\SDK\Model\CreateNotificationChannel;
use Fivexer\SDK\Model\CreateNotificationSequence;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\LearningFeedbackItem;
use Fivexer\SDK\Model\NotificationSequenceStep;
use Fivexer\SDK\Model\PatchSkill;
use Fivexer\SDK\Model\PatchWorker;
use Fivexer\SDK\Model\RequiredSkill;
use Fivexer\SDK\Model\SetTaskContext;
use Fivexer\SDK\Model\StartRun;
use Fivexer\SDK\Model\SuggestWorkers;
use Fivexer\SDK\Model\TaskReferenceInput;
use Fivexer\SDK\Model\UpdateNotificationChannel;
use Fivexer\SDK\Model\UpdateNotificationSequence;
use Fivexer\SDK\Model\UpsertSkill;
use Fivexer\SDK\Model\UpsertWorker;
use Fivexer\SDK\Model\WorkerLogin;
use Fivexer\SDK\Model\WorkerSkillAssignment;
use Fivexer\SDK\Model\WorkflowDefinitionInput;
use Fivexer\SDK\Model\WorkflowRouting;
use Fivexer\SDK\Model\WorkflowStep;
use PHPUnit\Framework\TestCase;

/**
 * How input models serialise onto the wire.
 *
 * The rule these all turn on: an optional field that was never set must be *absent*, never null.
 * Every partial-update endpoint keeps the stored value for an absent field, so a stray null
 * silently wipes data. Driving the models directly keeps these checks free of HTTP.
 */
final class RequestBuildingTest extends TestCase
{
    public function testAMinimalTaskSendsOnlyItsTags(): void
    {
        self::assertSame(['tags' => ['english']], (new CreateTask(['english']))->toArray());
    }

    public function testATaskCarriesGeoCidrAndRichDataTogether(): void
    {
        $body = (new CreateTask(['field']))
            ->priority(90)
            ->title('Fix the meter')
            ->description('Meter 41 is stuck')
            ->context(['meterId' => '41'])
            ->latitude(59.4)
            ->longitude(24.7)
            ->maxDistanceKm(25)
            ->requireGeo(true)
            ->allowedCidrs(['10.0.0.0/8'])
            ->skillThresholds(['electrical' => 3.0])
            ->vetoedWorkers(['agent_9'])
            ->meta(['ticket' => 'T-1'])
            ->references([(new TaskReferenceInput('https://crm/o/41'))->label('Order 41')])
            ->toArray();

        self::assertSame(90.0, $body['priority']);
        self::assertSame('Fix the meter', $body['title']);
        self::assertSame(24.7, $body['longitude']);
        self::assertTrue($body['requireGeo']);
        self::assertSame(['10.0.0.0/8'], $body['allowedCidrs']);
        self::assertSame(['agent_9'], $body['vetoedWorkers']);
        // The API assigns reference ids, so an input reference must not invent one.
        self::assertArrayNotHasKey('id', $body['references'][0]);
        self::assertSame('Order 41', $body['references'][0]['label']);
    }

    public function testATaskRequiresAtLeastOneTag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateTask([]);
    }

    public function testAnEmptyWorkerPatchSendsAnEmptyBodyRatherThanNulls(): void
    {
        // A body of nulls would clear tags, weights and skills instead of leaving them alone.
        self::assertSame([], (new PatchWorker())->toArray());
    }

    public function testPatchingOneWorkerFieldLeavesTheOthersAbsent(): void
    {
        self::assertSame(['maxBacklogSize' => 3], (new PatchWorker())->maxBacklogSize(3)->toArray());
    }

    public function testUpsertingAWorkerSerialisesSkillAssignments(): void
    {
        $body = (new UpsertWorker())
            ->id('agent_1')
            ->tags(['english'])
            ->routingWeights(['english' => 100.0])
            ->skills([new WorkerSkillAssignment('skl_1', 4)])
            ->ip('10.0.0.1')
            ->latitude(59.4)
            ->longitude(24.7)
            ->maxTravelDistanceKm(25)
            ->maxBacklogSize(5)
            ->toArray();

        self::assertSame(['skillId' => 'skl_1', 'level' => 4], $body['skills'][0]);
        self::assertSame(5, $body['maxBacklogSize']);
        self::assertSame('10.0.0.1', $body['ip']);
        self::assertSame(25.0, $body['maxTravelDistanceKm']);
    }

    public function testASkillWeightOverrideIsSentOnlyWhenSet(): void
    {
        $without = (new WorkerSkillAssignment('skl_1', 3))->toArray();
        $with = (new WorkerSkillAssignment('skl_1', 3))->weightOverride(80.0)->toArray();

        self::assertArrayNotHasKey('weightOverride', $without);
        self::assertSame(80.0, $with['weightOverride']);
    }

    public function testAnEmptyWorkerUpsertAsksTheApiToGenerateAnId(): void
    {
        self::assertSame([], (new UpsertWorker())->toArray());
    }

    public function testAnEmptyContextUpdateSendsAnEmptyBody(): void
    {
        self::assertSame([], (new SetTaskContext())->toArray());
    }

    public function testSettingContextSerialisesReferencesWithoutIds(): void
    {
        $body = (new SetTaskContext())
            ->title('Refund')
            ->description('Order 41')
            ->context(['orderId' => '41'])
            ->references([(new TaskReferenceInput('https://crm/o/41'))->contentType('text/html')])
            ->toArray();

        self::assertSame('Refund', $body['title']);
        self::assertSame(
            ['url' => 'https://crm/o/41', 'contentType' => 'text/html'],
            $body['references'][0],
        );
    }

    public function testACommentWithoutAttributionSendsOnlyTheBody(): void
    {
        self::assertSame(['body' => 'Called back'], (new AddComment('Called back'))->toArray());
    }

    public function testACommentAttributedToAWorkerCarriesTheWorkerId(): void
    {
        $body = (new AddComment('Called back'))->workerId('agent_1')->authorLabel('Ada')->toArray();

        self::assertSame('agent_1', $body['workerId']);
        self::assertSame('Ada', $body['authorLabel']);
    }

    public function testAnAttachmentWithoutWorkerAttributionOmitsTheWorkerId(): void
    {
        $body = (new CreateAttachment('a.pdf', 'application/pdf', 10))->toArray();

        self::assertArrayNotHasKey('workerId', $body);
        self::assertSame(10, $body['sizeBytes']);
        self::assertSame('a.pdf', $body['filename']);
    }

    public function testASkillWithoutADescriptionOmitsIt(): void
    {
        self::assertSame(
            ['key' => 'refunds', 'name' => 'Refunds'],
            (new UpsertSkill('refunds', 'Refunds'))->toArray(),
        );
    }

    public function testAnEmptySkillPatchSendsAnEmptyBody(): void
    {
        self::assertSame([], (new PatchSkill())->toArray());
        self::assertSame(['name' => 'X'], (new PatchSkill())->name('X')->toArray());
    }

    public function testStartingARunWithoutContextSendsAnEmptyBody(): void
    {
        self::assertSame([], (new StartRun())->toArray());
    }

    public function testStartingARunCarriesContextAndInitiator(): void
    {
        $body = (new StartRun())->context(['a' => 1])->initiatorWorkerId('agent_1')->toArray();

        self::assertSame(['context' => ['a' => 1], 'initiatorWorkerId' => 'agent_1'], $body);
    }

    public function testBulkFeedbackSerialisesSignalsAndRewardsIndependently(): void
    {
        $withReward = (new LearningFeedbackItem('t_1'))->reward(1.0)->toArray();
        $withSignals = (new LearningFeedbackItem('t_2'))->signals(['csat' => 0.9])->toArray();

        self::assertArrayNotHasKey('signals', $withReward);
        self::assertArrayNotHasKey('reward', $withSignals);
        self::assertSame('t_1', $withReward['taskId']);
        self::assertSame(['csat' => 0.9], $withSignals['signals']);
    }

    public function testSuggestingWorkersSerialisesRequiredSkillLevels(): void
    {
        $body = (new SuggestWorkers(['english']))
            ->priority(90.0)
            ->requiredSkills([new RequiredSkill('skl_1', 3)])
            ->vetoedWorkers(['agent_9'])
            ->limit(2)
            ->latitude(59.4)
            ->longitude(24.7)
            ->maxDistanceKm(25.0)
            ->requireGeo(true)
            ->allowedCidrs(['10.0.0.0/8'])
            ->toArray();

        self::assertSame(['skillId' => 'skl_1', 'minLevel' => 3], $body['requiredSkills'][0]);
        self::assertSame(['english'], $body['tags']);
        self::assertSame(2, $body['limit']);
    }

    public function testASequenceStepOffsetIsSentOnlyWhenGiven(): void
    {
        $without = (new NotificationSequenceStep('matched', 'task.expiring'))->toArray();
        $with = (new NotificationSequenceStep('matched', 'task.expiring'))->offsetMs(300000)->toArray();

        self::assertArrayNotHasKey('offsetMs', $without);
        self::assertSame(300000, $with['offsetMs']);
        self::assertSame('matched', $with['trigger']);
    }

    public function testCreatingASequenceWithoutOptionsSendsNameAndStepsOnly(): void
    {
        $body = (new CreateNotificationSequence('Escalate', []))->toArray();

        self::assertSame(['name' => 'Escalate', 'steps' => []], $body);
    }

    public function testCreatingASequenceSerialisesEachStep(): void
    {
        $body = (new CreateNotificationSequence('Escalate', [
            (new NotificationSequenceStep('matched', 'task.expiring'))->offsetMs(1000),
        ]))->enabled(true)->filterTags(['billing'])->toArray();

        self::assertSame(1000, $body['steps'][0]['offsetMs']);
        self::assertTrue($body['enabled']);
        self::assertSame(['billing'], $body['filterTags']);
    }

    public function testAnEmptySequenceUpdateSendsAnEmptyBody(): void
    {
        self::assertSame([], (new UpdateNotificationSequence())->toArray());
    }

    public function testUpdatingSequenceStepsSerialisesEachStep(): void
    {
        $body = (new UpdateNotificationSequence())
            ->name('Faster')
            ->enabled(false)
            ->steps([new NotificationSequenceStep('matched', 'e')])
            ->filterTags(['english'])
            ->toArray();

        self::assertSame(['trigger' => 'matched', 'eventType' => 'e'], $body['steps'][0]);
        self::assertFalse($body['enabled']);
    }

    public function testCreatingAChannelWithoutASecretOmitsIt(): void
    {
        $body = (new CreateNotificationChannel('webhook', 'https://hooks', ['task.matched']))->toArray();

        self::assertArrayNotHasKey('secret', $body);
        self::assertSame('webhook', $body['type']);
    }

    public function testAnEmptyChannelUpdateSendsAnEmptyBody(): void
    {
        self::assertSame([], (new UpdateNotificationChannel())->toArray());
    }

    public function testDisablingAChannelTouchesOnlyTheDisabledFlag(): void
    {
        self::assertSame(['disabled' => true], (new UpdateNotificationChannel())->disabled(true)->toArray());
    }

    public function testUpdatingAChannelCanRotateEverySetting(): void
    {
        $body = (new UpdateNotificationChannel())
            ->type('websocket')
            ->target('wss://x')
            ->events(['task.completed'])
            ->secret('whsec_new')
            ->toArray();

        self::assertSame('websocket', $body['type']);
        self::assertSame('whsec_new', $body['secret']);
        self::assertSame(['task.completed'], $body['events']);
    }

    public function testALoginAlwaysSendsAllThreeCredentials(): void
    {
        $login = new WorkerLogin('ws_1', 'agent_1', '4821');

        self::assertSame(
            ['workspaceId' => 'ws_1', 'workerId' => 'agent_1', 'pin' => '4821'],
            $login->toArray(),
        );
        self::assertSame('agent_1', $login->getWorkerId());
        self::assertSame('ws_1', $login->getWorkspaceId());
        self::assertSame('4821', $login->getPin());
    }

    // ---- workflow definitions: the tri-state successor ----

    public function testATerminalStepSerialisesAnExplicitNullSuccessor(): void
    {
        // `defaultNextStepId: null` is what ends a workflow — it must survive null-pruning.
        $step = (new WorkflowStep('done', 'Done'))->taskType('assignment')->endWorkflow()->toArray();

        self::assertArrayHasKey('defaultNextStepId', $step);
        self::assertNull($step['defaultNextStepId']);
    }

    public function testAStepWithNoDeclaredSuccessorOmitsTheKeyEntirely(): void
    {
        $step = (new WorkflowStep('a', 'A'))->toArray();

        self::assertSame(['id' => 'a', 'name' => 'A'], $step);
    }

    public function testNamingASuccessorAfterMarkingAStepTerminalClearsTheTerminalFlag(): void
    {
        $step = (new WorkflowStep('a', 'A'))->endWorkflow()->defaultNextStepId('b');

        self::assertFalse($step->isTerminal());
        self::assertSame('b', $step->toArray()['defaultNextStepId']);
    }

    public function testAStepSerialisesItsFullExecutionPolicy(): void
    {
        $step = (new WorkflowStep('review', 'Review'))
            ->taskType('external')
            ->external(['name' => 'compliance'])
            ->machineTask(['handler' => 'h'])
            ->assignmentTemplate(['tags' => ['english']])
            ->targetUser('initiator')
            ->routing([new WorkflowRouting('cond', 'next')])
            ->parallelStepIds(['a', 'b'])
            ->waitForAll(true)
            ->failurePolicy('retry')
            ->maxRetries(3)
            ->timeoutMs(300000)
            ->toArray();

        self::assertSame('external', $step['taskType']);
        self::assertSame(['name' => 'compliance'], $step['external']);
        self::assertSame('initiator', $step['targetUser']);
        self::assertSame(['condition' => 'cond', 'targetStepId' => 'next'], $step['routing'][0]);
        self::assertSame(['a', 'b'], $step['parallelStepIds']);
        self::assertTrue($step['waitForAll']);
        self::assertSame('retry', $step['failurePolicy']);
        self::assertSame(3, $step['maxRetries']);
        self::assertSame(300000, $step['timeoutMs']);
    }

    public function testAStepExposesEverythingItWasBuiltWith(): void
    {
        $step = (new WorkflowStep('review', 'Review'))
            ->taskType('external')
            ->external(['name' => 'compliance'])
            ->machineTask(['handler' => 'h'])
            ->assignmentTemplate(['tags' => ['english']])
            ->targetUser(['tag' => 'billing'])
            ->routing([new WorkflowRouting('cond', 'next')])
            ->parallelStepIds(['a'])
            ->waitForAll(false)
            ->failurePolicy('abort')
            ->maxRetries(1)
            ->timeoutMs(1000);

        self::assertSame('review', $step->getId());
        self::assertSame('Review', $step->getName());
        self::assertSame('external', $step->getTaskType());
        self::assertSame(['name' => 'compliance'], $step->getExternal());
        self::assertSame(['handler' => 'h'], $step->getMachineTask());
        self::assertSame(['tags' => ['english']], $step->getAssignmentTemplate());
        self::assertSame(['tag' => 'billing'], $step->getTargetUser());
        self::assertSame('next', $step->getRouting()[0]->targetStepId);
        self::assertSame(['a'], $step->getParallelStepIds());
        self::assertFalse($step->getWaitForAll());
        self::assertSame('abort', $step->getFailurePolicy());
        self::assertSame(1, $step->getMaxRetries());
        self::assertSame(1000, $step->getTimeoutMs());
        self::assertNull($step->getDefaultNextStepId());
    }

    public function testSavingADefinitionNeverPutsTheIdInTheBody(): void
    {
        // The URL is the id of record; a body id would be ambiguous.
        $body = (new WorkflowDefinitionInput('N', [new WorkflowStep('a', 'A')]))->toArray();

        self::assertArrayNotHasKey('id', $body);
        self::assertSame('N', $body['name']);
    }

    public function testADefinitionCarriesItsOptionalVersionAndTimeoutWhenSet(): void
    {
        $body = (new WorkflowDefinitionInput('N', [new WorkflowStep('a', 'A')]))
            ->version(3)
            ->initialStepId('a')
            ->defaultTimeoutMs(600000)
            ->metadata(['owner' => 'ops'])
            ->toArray();

        self::assertSame(3, $body['version']);
        self::assertSame('a', $body['initialStepId']);
        self::assertSame(600000, $body['defaultTimeoutMs']);
        self::assertSame(['owner' => 'ops'], $body['metadata']);
    }
}
