<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Model\AddComment;
use Fivexer\SDK\Model\CreateAttachment;
use Fivexer\SDK\Model\CreateNotificationChannel;
use Fivexer\SDK\Model\CreateNotificationSequence;
use Fivexer\SDK\Model\LearningFeedbackItem;
use Fivexer\SDK\Model\NotificationSequenceStep;
use Fivexer\SDK\Model\PatchSkill;
use Fivexer\SDK\Model\PatchWorker;
use Fivexer\SDK\Model\RequiredSkill;
use Fivexer\SDK\Model\SetTaskContext;
use Fivexer\SDK\Model\StartRun;
use Fivexer\SDK\Model\SuggestWorkers;
use Fivexer\SDK\Model\UpdateNotificationChannel;
use Fivexer\SDK\Model\UpdateNotificationSequence;
use Fivexer\SDK\Model\UpsertSkill;
use Fivexer\SDK\Model\WorkerSkillAssignment;
use Fivexer\SDK\Model\WorkflowDefinitionInput;
use Fivexer\SDK\Model\WorkflowStep;

/**
 * The /v1 surfaces beyond the original task/worker slice, driven end to end over HTTP.
 *
 * Black-box: call the public SDK API and assert both the parsed result and the exact request
 * the SDK emitted, since that wire shape *is* the contract.
 */
final class NewSurfacesTest extends ClientTestCase
{
    private const ATTACHMENT_JSON = '{"id":"att_1","taskId":"task_8fk2","filename":"receipt.pdf",'
        . '"contentType":"application/pdf","sizeBytes":6,"status":"pending",'
        . '"uploader":{"type":"api","id":null},"createdAt":1750001000000,"confirmedAt":null}';

    // ---- task context ----

    public function testReadingTheContextOfATaskReturnsItsReferences(): void
    {
        $this->enqueueJson(200, '{"taskId":"task_8fk2","title":"Refund request","description":null,'
            . '"context":{"orderId":"41"},"references":[{"id":"ref_1","url":"https://crm/o/41"}]}');

        $context = $this->client()->tasks()->context()->get('task_8fk2');

        self::assertSame('/v1/tasks/task_8fk2/context', $this->pathOf($this->lastRequest()));
        self::assertSame('Refund request', $context->title);
        self::assertSame('https://crm/o/41', $context->references[0]->url);
    }

    public function testSettingContextReplacesItWholesale(): void
    {
        $this->enqueueJson(200, '{"taskId":"task_8fk2","references":[]}');

        $this->client()->tasks()->context()->set('task_8fk2', (new SetTaskContext())->title('Refund'));

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(['title' => 'Refund'], $this->requestBodyJson($request));
    }

    public function testClearingContextSendsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->context()->clear('task_8fk2');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    // ---- comments ----

    public function testAddingACommentReturnsTheStoredCommentNotTheEnvelope(): void
    {
        $this->enqueueJson(201, '{"comment":{"id":"cmt_1","taskId":"task_8fk2",'
            . '"author":{"type":"worker","id":"agent_1","label":"Ada"},"body":"Called back",'
            . '"createdAt":1750001000000}}');

        $comment = $this->client()->tasks()->comments()
            ->add('task_8fk2', (new AddComment('Called back'))->workerId('agent_1'));

        self::assertSame('cmt_1', $comment->id);
        self::assertSame('Ada', $comment->author->label);
    }

    public function testListingCommentsReportsTheCursorForTheNextPage(): void
    {
        $this->enqueueJson(200, '{"comments":[],"nextCursor":"cursor_c1","hasMore":true}');

        $page = $this->client()->tasks()->comments()->list('task_8fk2', null, 1);

        self::assertSame('limit=1', $this->queryOf($this->lastRequest()));
        self::assertTrue($page->hasMore);
        self::assertSame('cursor_c1', $page->nextCursor);
    }

    public function testListingCommentsWithoutPagingSendsNoQuery(): void
    {
        $this->enqueueJson(200, '{"comments":[],"nextCursor":null,"hasMore":false}');

        $this->client()->tasks()->comments()->list('task_8fk2');

        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testRemovingACommentTargetsItById(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->comments()->remove('task_8fk2', 'cmt_1');

        self::assertSame('/v1/tasks/task_8fk2/comments/cmt_1', $this->pathOf($this->lastRequest()));
    }

    // ---- attachments ----

    public function testCreatingAnAttachmentReturnsWhereToPutTheBytes(): void
    {
        $this->enqueueJson(201, '{"attachment":' . self::ATTACHMENT_JSON . ',"upload":{'
            . '"url":"https://storage.test/att_1?sig=abc","method":"PUT",'
            . '"headers":{"content-type":"application/pdf"},"expiresAt":1750004600000}}');

        $created = $this->client()->tasks()->attachments()
            ->create('task_8fk2', new CreateAttachment('receipt.pdf', 'application/pdf', 6));

        self::assertSame('pending', $created->attachment->status);
        self::assertSame('https://storage.test/att_1?sig=abc', $created->upload->url);
    }

    public function testConfirmingAnAttachmentMarksItReady(): void
    {
        $this->enqueueJson(200, '{"attachment":{"id":"att_1","status":"ready","confirmedAt":2}}');

        $attachment = $this->client()->tasks()->attachments()->confirm('task_8fk2', 'att_1');

        self::assertSame('ready', $attachment->status);
        self::assertSame(
            '/v1/tasks/task_8fk2/attachments/att_1/confirm',
            $this->pathOf($this->lastRequest()),
        );
    }

    public function testListingAttachmentsUnwrapsTheEnvelope(): void
    {
        $this->enqueueJson(200, '{"attachments":[' . self::ATTACHMENT_JSON . ']}');

        $attachments = $this->client()->tasks()->attachments()->list('task_8fk2');

        self::assertCount(1, $attachments);
        self::assertSame('att_1', $attachments[0]->id);
    }

    public function testListingAttachmentsOfATaskWithNoneReturnsAnEmptyList(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->client()->tasks()->attachments()->list('task_8fk2'));
    }

    public function testDownloadingAnAttachmentReturnsAShortLivedUrl(): void
    {
        $this->enqueueJson(200, '{"url":"https://storage.test/dl","expiresAt":1}');

        $download = $this->client()->tasks()->attachments()->download('task_8fk2', 'att_1');

        self::assertSame('https://storage.test/dl', $download->url);
    }

    public function testRemovingAnAttachmentTargetsItById(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->attachments()->remove('task_8fk2', 'att_1');

        self::assertSame('/v1/tasks/task_8fk2/attachments/att_1', $this->pathOf($this->lastRequest()));
    }

    /** Queue the three hops of an upload: /v1 create, the storage PUT, /v1 confirm. */
    private function enqueueUploadFlow(int $storageStatus): void
    {
        $this->enqueueJson(201, '{"attachment":' . self::ATTACHMENT_JSON . ',"upload":{'
            . '"url":"https://storage.test/att_1","method":"PUT",'
            . '"headers":{"content-type":"application/pdf","x-amz-meta-task":"task_8fk2"},'
            . '"expiresAt":1}}');
        $this->enqueueEmpty($storageStatus);
        $this->enqueueJson(200, '{"attachment":{"id":"att_1","status":"ready"}}');
    }

    public function testUploadingAFileReservesStoresAndConfirmsItInOneCall(): void
    {
        $this->enqueueUploadFlow(200);

        $attachment = $this->client()->tasks()->attachments()
            ->upload('task_8fk2', 'hello!', 'receipt.pdf', 'application/pdf');

        self::assertSame('ready', $attachment->status);
        $paths = \array_map(fn ($r): string => (string) $r->getUri(), $this->recordedRequests());
        self::assertSame('https://api.fivexer.test/v1/tasks/task_8fk2/attachments', $paths[0]);
        self::assertSame('https://storage.test/att_1', $paths[1]);
        self::assertSame(
            'https://api.fivexer.test/v1/tasks/task_8fk2/attachments/att_1/confirm',
            $paths[2],
        );
    }

    public function testUploadingDerivesTheSizeFromThePayload(): void
    {
        $this->enqueueUploadFlow(200);

        $this->client()->tasks()->attachments()
            ->upload('task_8fk2', 'hello!', 'receipt.pdf', 'application/pdf');

        $create = $this->recordedRequests()[0];
        self::assertSame(6, $this->requestBodyJson($create)['sizeBytes']);
    }

    public function testUploadingSendsThePresignedHeadersVerbatimAndNoApiKey(): void
    {
        // The headers are part of the signature; the API key has no business at object storage.
        $this->enqueueUploadFlow(200);

        $this->client()->tasks()->attachments()
            ->upload('task_8fk2', 'hello!', 'receipt.pdf', 'application/pdf');

        $storagePut = $this->recordedRequests()[1];
        self::assertSame('PUT', $storagePut->getMethod());
        self::assertSame('task_8fk2', $storagePut->getHeaderLine('x-amz-meta-task'));
        self::assertSame('hello!', (string) $storagePut->getBody());
        self::assertSame('', $storagePut->getHeaderLine('Authorization'));
    }

    public function testAStorageRejectionSurfacesAsAnUploadFailureAndSkipsConfirmation(): void
    {
        $this->enqueueUploadFlow(403);

        try {
            $this->client()->tasks()->attachments()
                ->upload('task_8fk2', 'hello!', 'receipt.pdf', 'application/pdf');
            self::fail('expected the storage rejection to surface');
        } catch (FivexerApiException $e) {
            self::assertSame('upload_failed', $e->apiCode);
            self::assertSame(403, $e->statusCode);
        }

        // The record stays pending rather than being confirmed against bytes that never landed.
        self::assertCount(2, $this->recordedRequests());
    }

    public function testUploadingCanAttributeTheFileToAWorker(): void
    {
        $this->enqueueUploadFlow(200);

        $this->client()->tasks()->attachments()
            ->upload('task_8fk2', 'hello!', 'receipt.pdf', 'application/pdf', 'agent_1');

        self::assertSame('agent_1', $this->requestBodyJson($this->recordedRequests()[0])['workerId']);
    }

    // ---- operator overrides ----

    public function testAssigningAQueuedTaskReportsNoPreviousWorker(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"pending","workerId":"agent_1",'
            . '"previousWorkerId":null}');

        $result = $this->client()->tasks()->assign('task_8fk2', 'agent_1');

        $request = $this->lastRequest();
        self::assertSame('/v1/tasks/task_8fk2/assign', $this->pathOf($request));
        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($request));
        self::assertNull($result->previousWorkerId);
        self::assertSame('agent_1', $result->workerId);
    }

    public function testReassigningAPendingTaskNamesTheWorkerItWasTakenFrom(): void
    {
        // Knowing who lost the task is what lets an operator explain the move afterwards.
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"pending","workerId":"agent_2",'
            . '"previousWorkerId":"agent_1"}');

        $result = $this->client()->tasks()->assign('task_8fk2', 'agent_2');

        self::assertSame('agent_1', $result->previousWorkerId);
    }

    public function testForcingAnAssignmentSendsTheOverrideFlag(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","status":"pending","workerId":"agent_1"}');

        $this->client()->tasks()->assign('task_8fk2', 'agent_1', true);

        self::assertSame(
            ['workerId' => 'agent_1', 'force' => true],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function testAnUnforcedAssignmentRespectsTheBacklogCap(): void
    {
        $this->enqueueJson(409, '{"error":{"code":"worker_backlog_full","message":"at limit"}}');

        $this->expectException(FivexerApiException::class);
        $this->client()->tasks()->assign('task_8fk2', 'agent_1');
    }

    public function testRepricingATaskReturnsItsNewPriority(): void
    {
        $this->enqueueJson(200, '{"id":"task_8fk2","priority":95}');

        $result = $this->client()->tasks()->setPriority('task_8fk2', 95);

        self::assertSame('PATCH', $this->lastRequest()->getMethod());
        self::assertSame(['priority' => 95.0], $this->requestBodyJson($this->lastRequest()));
        self::assertSame(95.0, $result->priority);
    }

    public function testSuggestingWorkersExplainsWhyEachOneIsOrIsNotEligible(): void
    {
        $this->enqueueJson(200, '{"tags":["english"],"priority":90,"workers":['
            . '{"workerId":"agent_1","eligible":true,"score":180,"effectivePriority":90,'
            . '"reasons":[{"tag":"english"}]},'
            . '{"workerId":"agent_2","eligible":false,"score":0,"effectivePriority":90,"reasons":[]}]}');

        $result = $this->client()->tasks()->suggestWorkers(
            (new SuggestWorkers(['english']))->requiredSkills([new RequiredSkill('skl_1', 3)])
        );

        self::assertSame('/v1/tasks/suggest-workers', $this->pathOf($this->lastRequest()));
        self::assertTrue($result->workers[0]->eligible);
        self::assertFalse($result->workers[1]->eligible);
    }

    // ---- worker detail and availability ----

    public function testReadingAWorkerShowsTheirLoadAgainstTheirCap(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1","tags":["english"],"routingWeights":{"english":100},'
            . '"skills":[{"skillId":"skl_1","key":"refunds","name":"Refunds","level":4,'
            . '"weightOverride":null,"weight":80}],"maxBacklogSize":5,"available":true,"queueDepth":2}');

        $detail = $this->client()->workers()->get('agent_1');

        self::assertSame('/v1/workers/agent_1', $this->pathOf($this->lastRequest()));
        self::assertSame(2, $detail->queueDepth);
        self::assertNotNull($detail->skills);
        self::assertSame('Refunds', $detail->skills[0]->name);
    }

    public function testPatchingAWorkerChangesOnlyTheFieldsSupplied(): void
    {
        $this->enqueueJson(200, '{"id":"agent_1"}');

        $workerId = $this->client()->workers()->patch(
            'agent_1',
            (new PatchWorker())->skills([new WorkerSkillAssignment('skl_1', 5)])
        );

        self::assertSame('PATCH', $this->lastRequest()->getMethod());
        self::assertSame(
            ['skills' => [['skillId' => 'skl_1', 'level' => 5]]],
            $this->requestBodyJson($this->lastRequest()),
        );
        self::assertSame('agent_1', $workerId);
    }

    public function testPausingAWorkerKeepsTheirBacklog(): void
    {
        // "Back in ten minutes": stop matching new work, but leave what they already hold.
        $this->enqueueJson(200, '{"id":"agent_1","available":false}');

        $result = $this->client()->workers()->setAvailability('agent_1', false);

        self::assertSame(['available' => false], $this->requestBodyJson($this->lastRequest()));
        self::assertFalse($result->available);
        self::assertNull($result->releasedTaskIds);
    }

    public function testPausingAndReleasingReportsWhichTasksWereRequeued(): void
    {
        // "Gone for the day": the unaccepted backlog goes back to the queue for others.
        $this->enqueueJson(200, '{"id":"agent_1","available":false,'
            . '"releasedTaskIds":["task_8fk2","task_9aa3"]}');

        $result = $this->client()->workers()->setAvailability('agent_1', false, true);

        self::assertSame(
            ['available' => false, 'releaseBacklog' => true],
            $this->requestBodyJson($this->lastRequest()),
        );
        self::assertSame(['task_8fk2', 'task_9aa3'], $result->releasedTaskIds);
    }

    public function testResumingAWorkerNeverMentionsBacklogRelease(): void
    {
        // The API rejects releaseBacklog on resume, so the SDK must not send it either way.
        $this->enqueueJson(200, '{"id":"agent_1","available":true}');

        $this->client()->workers()->setAvailability('agent_1', true);

        self::assertSame(['available' => true], $this->requestBodyJson($this->lastRequest()));
    }

    public function testAskingToReleaseABacklogWhileResumingIsRejectedByTheApi(): void
    {
        $this->enqueueJson(400, '{"error":{"code":"invalid_body","message":"only when pausing"}}');

        $this->expectException(FivexerApiException::class);
        $this->client()->workers()->setAvailability('agent_1', true, true);
    }

    // ---- skills ----

    public function testDefiningASkillReturnsTheStoredRecord(): void
    {
        $this->enqueueJson(201, '{"id":"skl_1","key":"refunds","name":"Refunds",'
            . '"description":"Handles refunds","createdAt":"2026-07-24T10:00:00.000Z"}');

        $skill = $this->client()->skills()->create(new UpsertSkill('refunds', 'Refunds'));

        self::assertSame('skl_1', $skill->id);
        self::assertSame('refunds', $skill->key);
    }

    public function testSearchingTheCatalogueFiltersByQueryAndLimit(): void
    {
        $this->enqueueJson(200, '{"skills":[]}');

        $this->client()->skills()->list('ref', 10);

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('q=ref', $query);
        self::assertStringContainsString('limit=10', $query);
    }

    public function testListingTheWholeCatalogueSendsNoFilters(): void
    {
        $this->enqueueJson(200, '{"skills":[]}');

        self::assertSame([], $this->client()->skills()->list());
        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testRenamingASkillLeavesItsKeyUntouched(): void
    {
        // The key is the stable identifier workers are matched on; only the label changes.
        $this->enqueueJson(200, '{"id":"skl_1","key":"refunds","name":"Refunds & credits"}');

        $skill = $this->client()->skills()->patch('skl_1', (new PatchSkill())->name('Refunds & credits'));

        self::assertArrayNotHasKey('key', $this->requestBodyJson($this->lastRequest()));
        self::assertSame('refunds', $skill->key);
    }

    public function testRemovingASkillSendsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->skills()->remove('skl_1');

        self::assertSame('/v1/skills/skl_1', $this->pathOf($this->lastRequest()));
    }

    public function testSuggestingCompanionsJoinsTheSelectedListIntoOneParameter(): void
    {
        $this->enqueueJson(200, '{"skills":[]}');

        $this->client()->skills()->suggest(['billing', 'english'], 5);

        self::assertStringContainsString(
            'selected=billing%2Cenglish',
            $this->queryOf($this->lastRequest()),
        );
    }

    public function testSuggestingWithNothingChosenYetOmitsTheSelectedParameter(): void
    {
        $this->enqueueJson(200, '{"skills":[]}');
        self::assertSame([], $this->client()->skills()->suggest());
        self::assertSame('', $this->queryOf($this->lastRequest()));

        $this->enqueueJson(200, '{"skills":[]}');
        $this->client()->skills()->suggest([]);
        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    // ---- workflows and runs ----

    public function testListingDefinitionsReturnsIdAndNameOnly(): void
    {
        $this->enqueueJson(200, '{"workflows":[{"id":"wf_1","name":"Onboarding"}]}');

        $workflows = $this->client()->workflows()->list();

        self::assertSame('wf_1', $workflows[0]->id);
        self::assertSame('Onboarding', $workflows[0]->name);
    }

    public function testReadingADefinitionPreservesWhichStepEndsTheWorkflow(): void
    {
        $this->enqueueJson(200, '{"id":"wf_1","name":"W","version":2,"initialStepId":"collect",'
            . '"steps":[{"id":"collect","name":"C","defaultNextStepId":"review"},'
            . '{"id":"review","name":"R","defaultNextStepId":null}]}');

        $definition = $this->client()->workflows()->get('wf_1');

        self::assertFalse($definition->steps[0]->isTerminal());
        self::assertTrue($definition->steps[1]->isTerminal());
    }

    public function testSavingADefinitionPutsItAtTheUrlThatNamesIt(): void
    {
        $this->enqueueJson(200, '{"id":"wf_1","name":"W","version":1,"initialStepId":"a","steps":[]}');

        $this->client()->workflows()->save(
            'wf_1',
            (new WorkflowDefinitionInput('W', [new WorkflowStep('a', 'A')]))->initialStepId('a')
        );

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/v1/workflows/wf_1', $this->pathOf($request));
        self::assertArrayNotHasKey('id', $this->requestBodyJson($request));
    }

    public function testSavingAnUnreachableGraphIsRejectedByTheEngine(): void
    {
        $this->enqueueJson(400, '{"error":{"code":"invalid_workflow","message":"bad graph"}}');

        try {
            $this->client()->workflows()->save('wf_x', new WorkflowDefinitionInput('B', []));
            self::fail('expected the engine to reject the graph');
        } catch (FivexerApiException $e) {
            self::assertSame('invalid_workflow', $e->apiCode);
        }
    }

    public function testDeletingADefinitionSendsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->workflows()->remove('wf_1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    public function testStartingARunReturnsTheFirstActiveStep(): void
    {
        $this->enqueueJson(201, '{"id":"run_1","workflowId":"wf_1","status":"active",'
            . '"currentStepId":"collect","initiatorWorkerId":"agent_1","definitionVersion":1,'
            . '"createdAt":1,"updatedAt":1}');

        $run = $this->client()->workflows()->run('wf_1', (new StartRun())->initiatorWorkerId('agent_1'));

        self::assertSame('/v1/workflows/wf_1/runs', $this->pathOf($this->lastRequest()));
        self::assertSame('collect', $run->currentStepId);
    }

    public function testStartingARunWithNoInputStillPostsABody(): void
    {
        $this->enqueueJson(201, '{"id":"run_1","workflowId":"wf_1","status":"active"}');

        $this->client()->workflows()->run('wf_1');

        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function testListingOneDefinitionsRunsFiltersByStatus(): void
    {
        $this->enqueueJson(200, '{"runs":[],"nextCursor":null}');

        $page = $this->client()->workflows()->listRuns('wf_1', 'active', null, 25);

        $request = $this->lastRequest();
        self::assertSame('/v1/workflows/wf_1/runs', $this->pathOf($request));
        self::assertStringContainsString('status=active', $this->queryOf($request));
        self::assertNull($page->nextCursor);
    }

    public function testListingRunsAcrossTheWorkspaceCanFilterByDefinition(): void
    {
        $this->enqueueJson(200, '{"runs":[],"nextCursor":"cursor_r1"}');

        $page = $this->client()->runs()->list('completed', 'wf_1');

        $request = $this->lastRequest();
        self::assertSame('/v1/workflow-runs', $this->pathOf($request));
        self::assertStringContainsString('workflowId=wf_1', $this->queryOf($request));
        self::assertSame('cursor_r1', $page->nextCursor);
    }

    public function testReadingARunReturnsTheStepsItHasCompleted(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","workflowId":"wf_1","status":"completed",'
            . '"history":[{"stepId":"collect","taskId":"t_1","workerId":"agent_1","completedAt":1}]}');

        $run = $this->client()->runs()->get('run_1');

        self::assertSame('agent_1', $run->history[0]->workerId);
    }

    public function testTheStepViewReportsWhichStepIsWaitingOnACallback(): void
    {
        $this->enqueueJson(200, '{"runId":"run_1","status":"active","steps":['
            . '{"stepId":"review","name":"R","taskType":"external","state":"awaiting_callback"}]}');

        $view = $this->client()->runs()->steps('run_1');

        self::assertSame('awaiting_callback', $view->steps[0]->state);
    }

    public function testCancellingARunStopsIt(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","status":"cancelled"}');

        $run = $this->client()->runs()->cancel('run_1');

        self::assertSame('/v1/workflow-runs/run_1/cancel', $this->pathOf($this->lastRequest()));
        self::assertSame('cancelled', $run->status);
    }

    public function testCompletingACallbackStepAdvancesTheRun(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","status":"active","currentStepId":"notify"}');

        $run = $this->client()->runs()->completeStep('run_1', 'review', ['approved' => true]);

        $request = $this->lastRequest();
        self::assertSame('/v1/workflow-runs/run_1/steps/review/complete', $this->pathOf($request));
        self::assertSame(['data' => ['approved' => true]], $this->requestBodyJson($request));
        self::assertSame('notify', $run->currentStepId);
    }

    public function testCompletingACallbackStepWithoutDataSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","status":"active"}');

        $this->client()->runs()->completeStep('run_1', 'review');

        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function testFailingACallbackStepFailsTheRun(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","status":"failed"}');

        $run = $this->client()->runs()->failStep('run_1', 'review', 'compliance unreachable');

        self::assertSame(['error' => 'compliance unreachable'], $this->requestBodyJson($this->lastRequest()));
        self::assertSame('failed', $run->status);
    }

    public function testFailingACallbackStepWithoutAReasonSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"id":"run_1","status":"failed"}');

        $this->client()->runs()->failStep('run_1', 'review');

        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    // ---- learning ----

    public function testReadingLearningStatusReportsWhetherItIsShadowing(): void
    {
        $this->enqueueJson(200, '{"enabled":true,"shadowMode":true,"autoWeights":false,'
            . '"stats":{"decisions":1200,"rewards":800,"totalReward":940.5,"averageReward":1.175},'
            . '"modelSize":64}');

        $status = $this->client()->learning()->status();

        self::assertTrue($status->shadowMode);
        self::assertNotNull($status->stats);
        self::assertSame(1.175, $status->stats->averageReward);
    }

    public function testPerWorkerStatsAreScopedToThatWorker(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","skills":[]}');

        $stats = $this->client()->learning()->workerStats('agent_1');

        self::assertSame('/v1/learning/workers/agent_1', $this->pathOf($this->lastRequest()));
        self::assertSame('agent_1', $stats->workerId);
    }

    public function testPreviewingWeightsForOneWorkerScopesTheRequest(): void
    {
        $this->enqueueJson(200, '{"workers":[{"workerId":"agent_1","current":{},"learned":{}}]}');

        $preview = $this->client()->learning()->previewWeights('agent_1');

        self::assertSame('workerId=agent_1', $this->queryOf($this->lastRequest()));
        self::assertSame('agent_1', $preview->workers[0]->workerId);
    }

    public function testPreviewingWeightsForEveryoneSendsNoScope(): void
    {
        $this->enqueueJson(200, '{"workers":[]}');

        $this->client()->learning()->previewWeights();

        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testApplyingLearnedWeightsReportsWhatChangedPerWorker(): void
    {
        $this->enqueueJson(200, '{"applied":{"agent_1":{"english":118}}}');

        $applied = $this->client()->learning()->applyWeights(['agent_1']);

        self::assertSame(['workerIds' => ['agent_1']], $this->requestBodyJson($this->lastRequest()));
        self::assertSame(['agent_1' => ['english' => 118]], $applied);
    }

    public function testApplyingWeightsToEveryWorkerSendsAnEmptyBody(): void
    {
        $this->enqueueJson(200, '{"applied":{}}');

        self::assertSame([], $this->client()->learning()->applyWeights());
        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function testReportingOutcomeSignalsAgainstATask(): void
    {
        $this->enqueueJson(200, '{"ok":true}');

        self::assertTrue($this->client()->learning()->feedback('task_8fk2', ['csat' => 0.9]));
        self::assertSame('/v1/tasks/task_8fk2/feedback', $this->pathOf($this->lastRequest()));
    }

    public function testReportingADirectRewardAgainstATask(): void
    {
        $this->enqueueJson(200, '{"ok":true}');

        self::assertTrue($this->client()->learning()->reward('task_8fk2', 1.5));
        self::assertSame(['reward' => 1.5], $this->requestBodyJson($this->lastRequest()));
    }

    public function testBulkFeedbackReportsFailuresPerItemRatherThanFailingTheBatch(): void
    {
        $this->enqueueJson(200, '{"results":[{"taskId":"task_8fk2","ok":true},'
            . '{"taskId":"missing","ok":false,"error":"no decision recorded"}]}');

        $results = $this->client()->learning()->feedbackBulk([
            (new LearningFeedbackItem('task_8fk2'))->reward(1.0),
            (new LearningFeedbackItem('missing'))->signals(['csat' => 1.0]),
        ]);

        self::assertTrue($results[0]->ok);
        self::assertNull($results[0]->error);
        self::assertFalse($results[1]->ok);
        self::assertSame('no decision recorded', $results[1]->error);
    }

    public function testResettingDiscardsTheLearnedModel(): void
    {
        $this->enqueueJson(200, '{"ok":true}');

        self::assertTrue($this->client()->learning()->reset());
        self::assertSame('/v1/learning/reset', $this->pathOf($this->lastRequest()));
    }

    public function testARefusedResetIsReportedAsNotOk(): void
    {
        $this->enqueueJson(200, '{"ok":false}');

        self::assertFalse($this->client()->learning()->reset());
    }

    // ---- notifications ----

    public function testListingSequencesUnwrapsTheEnvelope(): void
    {
        $this->enqueueJson(200, '{"sequences":[{"id":"seq_1","name":"Escalate","enabled":true,'
            . '"steps":[{"trigger":"matched","offsetMs":300000,"eventType":"task.expiring"}],'
            . '"filterTags":["billing"]}]}');

        $sequences = $this->client()->notifications()->sequences()->list();

        self::assertSame('seq_1', $sequences[0]->id);
        self::assertSame(300000, $sequences[0]->steps[0]->getOffsetMs());
        self::assertSame(['billing'], $sequences[0]->filterTags);
    }

    public function testCreatingASequenceSchedulesItsStepsRelativeToATrigger(): void
    {
        $this->enqueueJson(201, '{"id":"seq_1","name":"Escalate","enabled":true,"steps":[]}');

        $this->client()->notifications()->sequences()->create(
            (new CreateNotificationSequence('Escalate', [
                (new NotificationSequenceStep('matched', 'task.expiring'))->offsetMs(300000),
            ]))->filterTags(['billing'])
        );

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame(300000, $body['steps'][0]['offsetMs']);
        self::assertSame(['billing'], $body['filterTags']);
    }

    public function testReadingASequenceById(): void
    {
        $this->enqueueJson(200, '{"id":"seq_1","name":"Escalate","enabled":true,"steps":[]}');

        self::assertSame('Escalate', $this->client()->notifications()->sequences()->get('seq_1')->name);
        self::assertSame('/v1/notification-sequences/seq_1', $this->pathOf($this->lastRequest()));
    }

    public function testDisablingASequenceLeavesItsStepsIntact(): void
    {
        // A PATCH with only `enabled` must not blank the steps it does not mention.
        $this->enqueueJson(200, '{"id":"seq_1","name":"E","enabled":false,'
            . '"steps":[{"trigger":"matched","eventType":"e"}]}');

        $sequence = $this->client()->notifications()->sequences()
            ->update('seq_1', (new UpdateNotificationSequence())->enabled(false));

        self::assertSame(['enabled' => false], $this->requestBodyJson($this->lastRequest()));
        self::assertFalse($sequence->enabled);
        self::assertCount(1, $sequence->steps);
    }

    public function testRemovingASequenceSendsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->notifications()->sequences()->remove('seq_1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    public function testListingChannelsUnwrapsTheEnvelope(): void
    {
        $this->enqueueJson(200, '{"channels":[{"id":"ch_1","type":"webhook","target":"https://h",'
            . '"events":["task.matched"],"disabled":false}]}');

        $channels = $this->client()->notifications()->channels()->list();

        self::assertSame('ch_1', $channels[0]->id);
        self::assertSame(['task.matched'], $channels[0]->events);
        self::assertFalse($channels[0]->disabled);
    }

    public function testCreatingAWebhookChannelSendsTheSigningSecret(): void
    {
        $this->enqueueJson(201, '{"id":"ch_1","type":"webhook","target":"https://h","events":[]}');

        $this->client()->notifications()->channels()->create(
            (new CreateNotificationChannel('webhook', 'https://h', ['task.matched']))->secret('whsec_abc')
        );

        self::assertSame('whsec_abc', $this->requestBodyJson($this->lastRequest())['secret']);
    }

    public function testReadingAChannelById(): void
    {
        $this->enqueueJson(200, '{"id":"ch_1","type":"webhook","target":"https://h","events":[]}');

        self::assertSame('https://h', $this->client()->notifications()->channels()->get('ch_1')->target);
    }

    public function testDisablingAChannelStopsDeliveryWithoutDeletingIt(): void
    {
        $this->enqueueJson(200, '{"id":"ch_1","type":"webhook","target":"https://h",'
            . '"events":[],"disabled":true}');

        $channel = $this->client()->notifications()->channels()
            ->update('ch_1', (new UpdateNotificationChannel())->disabled(true));

        self::assertSame(['disabled' => true], $this->requestBodyJson($this->lastRequest()));
        self::assertTrue($channel->disabled);
    }

    public function testRemovingAChannelSendsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->notifications()->channels()->remove('ch_1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    // ---- history, presence, breaks ----

    public function testTheTimeseriesCarriesTheRequestedWindow(): void
    {
        $this->enqueueJson(200, '{"from":"a","to":"b","bucket":"hour","buckets":[]}');

        $result = $this->client()->history()->timeseries('2026-07-23T00:00:00Z', '2026-07-24T00:00:00Z', 'hour');

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('bucket=hour', $query);
        self::assertStringContainsString('from=2026-07-23', $query);
        self::assertSame('hour', $result->bucket);
    }

    public function testAskingForTheDefaultWindowSendsNoParameters(): void
    {
        $this->enqueueJson(200, '{"from":"a","to":"b","bucket":"hour","buckets":[]}');

        $this->client()->history()->timeseries();

        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testWorkerProductivityUsesTheSameWindow(): void
    {
        $this->enqueueJson(200, '{"from":"a","to":"b","workers":[]}');

        $this->client()->history()->workers('2026-07-23T00:00:00Z');

        self::assertSame('/v1/stats/workers', $this->pathOf($this->lastRequest()));
        self::assertStringContainsString('from=2026-07-23', $this->queryOf($this->lastRequest()));
    }

    public function testAWorkerRowMergesTheArchiveTheDayCountersAndTheShiftLog(): void
    {
        // Three sources in one row: throughput, how they responded, and how long they worked.
        $this->enqueueJson(200, '{"from":"a","to":"b","workers":[{"workerId":"agent_1",'
            . '"completed":12,"cancelled":1,"avgWaitMs":4200,"avgHandleMs":90000,'
            . '"p50HandleMs":80000,"p95HandleMs":210000,"offered":20,"accepted":16,'
            . '"rejected":2,"failed":1,"expired":1,"released":0,"acceptanceRate":0.8,'
            . '"onShiftMs":28800000,"breakMs":1800000,"workingMs":27000000,"shiftCount":1,'
            . '"utilization":0.65}]}');

        $row = $this->client()->history()->workers()->workers[0];

        self::assertSame(80000.0, $row->p50HandleMs);
        self::assertSame(210000.0, $row->p95HandleMs);
        self::assertSame(20, $row->offered);
        self::assertSame(16, $row->accepted);
        self::assertSame(2, $row->rejected);
        self::assertSame(1, $row->failed);
        self::assertSame(1, $row->expired);
        self::assertSame(0, $row->released);
        self::assertSame(0.8, $row->acceptanceRate);
        self::assertSame(28800000, $row->onShiftMs);
        self::assertSame(1800000, $row->breakMs);
        self::assertSame(27000000, $row->workingMs);
        self::assertSame(1, $row->shiftCount);
        self::assertSame(0.65, $row->utilization);
    }

    public function testAWorkerWhoWasOnShiftAndFinishedNothingStillHasARow(): void
    {
        // The row set is the union of the three sources, so worked time alone earns a row.
        $this->enqueueJson(200, '{"from":"a","to":"b","workers":[{"workerId":"agent_9",'
            . '"completed":0,"cancelled":0,"avgWaitMs":null,"avgHandleMs":null,'
            . '"p50HandleMs":null,"p95HandleMs":null,"acceptanceRate":null,'
            . '"onShiftMs":3600000,"workingMs":3600000,"shiftCount":1,"utilization":null}]}');

        $row = $this->client()->history()->workers()->workers[0];

        // Null, not 0.0: nobody offered them anything, which is not "they refused everything".
        self::assertNull($row->acceptanceRate);
        self::assertNull($row->utilization);
        self::assertNull($row->p95HandleMs);
        self::assertSame(0, $row->offered);
        self::assertSame(3600000, $row->workingMs);
    }

    public function testACrewFilterNarrowsTheWorkerReport(): void
    {
        $this->enqueueJson(200, '{"from":"a","to":"b","workers":[]}');

        $this->client()->history()->workers(null, null, 'day', 'team_night');

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('teamId=team_night', $query);
        self::assertStringContainsString('bucket=day', $query);
        self::assertStringNotContainsString('from=', $query);
    }

    public function testAWorkerTimeseriesCarriesWorkedTimeOnDayBuckets(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","from":"a","to":"b","bucket":"day",'
            . '"buckets":[{"bucketStart":"2026-07-23T00:00:00.000Z","completed":12,'
            . '"cancelled":1,"avgWaitMs":4200,"p50WaitMs":3000,"p95WaitMs":11000,'
            . '"avgHandleMs":90000,"onShiftMs":28800000,"breakMs":1800000,'
            . '"workingMs":27000000,"offered":20,"accepted":16,"rejected":2,"failed":1,'
            . '"expired":1,"released":0}]}');

        $result = $this->client()->history()->workerTimeseries('agent_1', null, null, 'day');

        $request = $this->lastRequest();
        self::assertSame('/v1/stats/workers/agent_1/timeseries', $this->pathOf($request));
        self::assertSame('bucket=day', $this->queryOf($request));
        self::assertSame('agent_1', $result->workerId);
        self::assertSame('day', $result->bucket);
        self::assertSame('a', $result->from);
        self::assertSame('b', $result->to);
        $bucket = $result->buckets[0];
        self::assertSame(12, $bucket->completed);
        self::assertSame(3000.0, $bucket->p50WaitMs);
        self::assertSame(28800000, $bucket->onShiftMs);
        self::assertSame(1800000, $bucket->breakMs);
        self::assertSame(27000000, $bucket->workingMs);
        self::assertSame(20, $bucket->offered);
        self::assertSame(16, $bucket->accepted);
        self::assertSame(2, $bucket->rejected);
        self::assertSame(1, $bucket->failed);
        self::assertSame(1, $bucket->expired);
        self::assertSame(0, $bucket->released);
    }

    public function testAnHourBucketReportsNoWorkedTimeRatherThanZero(): void
    {
        // Worked time is day-grained. Zero here would claim the worker was never on shift.
        $this->enqueueJson(200, '{"workerId":"agent_1","from":"a","to":"b","bucket":"hour",'
            . '"buckets":[{"bucketStart":"2026-07-23T01:00:00.000Z","completed":2,'
            . '"cancelled":0,"avgWaitMs":null,"p50WaitMs":null,"p95WaitMs":null,'
            . '"avgHandleMs":null}]}');

        $bucket = $this->client()->history()->workerTimeseries('agent_1')->buckets[0];

        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertNull($bucket->onShiftMs);
        self::assertNull($bucket->breakMs);
        self::assertNull($bucket->workingMs);
        self::assertNull($bucket->offered);
        self::assertNull($bucket->accepted);
        self::assertNull($bucket->rejected);
        self::assertNull($bucket->failed);
        self::assertNull($bucket->expired);
        self::assertNull($bucket->released);
        self::assertSame(2, $bucket->completed);
    }

    public function testHistoryOnADataPlaneOnlyDeploymentIsReportedAsUnavailable(): void
    {
        // 501 here is a deployment fact, not a bug — the error code has to say which.
        $this->enqueueJson(501, '{"error":{"code":"history_unavailable","message":"needs control plane"}}');

        try {
            $this->client()->history()->timeseries();
            self::fail('expected history to report itself unavailable');
        } catch (FivexerApiException $e) {
            self::assertSame('history_unavailable', $e->apiCode);
            self::assertSame(501, $e->statusCode);
        }
    }

    public function testTheSupervisorPresenceViewSeparatesPausedFromOnBreak(): void
    {
        // Paused is an operator action; on-break is the worker's own. Not the same thing.
        $this->enqueueJson(200, '{"workers":[{"workerId":"a","label":"Ada","status":"paused"}],'
            . '"counts":{"working":0,"onBreak":0,"paused":1,"total":1}}');

        $presence = $this->client()->team()->presence();

        self::assertSame('/v1/team/presence', $this->pathOf($this->lastRequest()));
        self::assertSame(1, $presence->counts->paused);
        self::assertSame('paused', $presence->workers[0]->status);
    }

    public function testBreakMetricsRollUpPerWorkerForAWindow(): void
    {
        $this->enqueueJson(200, '{"workers":[],"totalBreakMs":0,"breakCount":0,"activeCount":1}');

        $metrics = $this->client()->breaks()->metrics('2026-07-24T00:00:00Z', '2026-07-24T23:59:59Z');

        self::assertStringContainsString('from=2026-07-24', $this->queryOf($this->lastRequest()));
        self::assertSame(1, $metrics->activeCount);
    }

    public function testPresenceCanBeNarrowedToOneCrew(): void
    {
        $this->enqueueJson(200, '{"workers":[],"counts":{"working":0,"onBreak":0,"paused":0,'
            . '"total":0}}');

        $this->client()->team()->presence('team_night');

        $request = $this->lastRequest();
        self::assertSame('/v1/team/presence', $this->pathOf($request));
        self::assertSame('teamId=team_night', $this->queryOf($request));
    }

    public function testBreakMetricsCanBeNarrowedToACrewAndAWorker(): void
    {
        $this->enqueueJson(200, '{"workers":[],"totalBreakMs":0,"breakCount":0,"activeCount":0}');

        $this->client()->breaks()->metrics(null, null, 'team_night', 'agent_2');

        $query = $this->queryOf($this->lastRequest());
        self::assertStringContainsString('teamId=team_night', $query);
        self::assertStringContainsString('workerId=agent_2', $query);
        self::assertStringNotContainsString('from=', $query);
    }

    public function testBreakMetricsDefaultToToday(): void
    {
        $this->enqueueJson(200, '{"workers":[],"totalBreakMs":0,"breakCount":0,"activeCount":0}');

        self::assertSame(0, $this->client()->breaks()->metrics()->breakCount);
        self::assertSame('', $this->queryOf($this->lastRequest()));
    }
}
