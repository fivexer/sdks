<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\Attachment;
use Fivexer\SDK\Model\AttachmentDownload;
use Fivexer\SDK\Model\Comment;
use Fivexer\SDK\Model\CommentPage;
use Fivexer\SDK\Model\CreatedAttachment;
use Fivexer\SDK\Model\LearningStatus;
use Fivexer\SDK\Model\QuotaInfo;
use Fivexer\SDK\Model\Skill;
use Fivexer\SDK\Model\StatsTimeseriesResult;
use Fivexer\SDK\Model\SuggestWorkersResult;
use Fivexer\SDK\Model\Task;
use Fivexer\SDK\Model\TaskContext;
use Fivexer\SDK\Model\TeamPresence;
use Fivexer\SDK\Model\WorkerBreakToday;
use Fivexer\SDK\Model\WorkerDetail;
use Fivexer\SDK\Model\WorkerLearningStats;
use Fivexer\SDK\Model\WorkerStatsResult;
use Fivexer\SDK\Model\WorkflowDefinition;
use Fivexer\SDK\Model\WorkflowRun;
use Fivexer\SDK\Model\WorkflowRunSteps;
use Fivexer\SDK\Model\WorkflowStep;
use Fivexer\SDK\Model\WorkspaceBreakMetrics;
use Fivexer\SDK\Model\WorkspaceStats;
use PHPUnit\Framework\TestCase;

/**
 * How the SDK reads API responses back into models.
 *
 * Parsing is where a wrong assumption costs the caller silently: a nullable field read as a
 * default, an optional list flattened to empty, a nested object dropped. Each test here pins a
 * distinction the API actually makes.
 */
final class ResponseParsingTest extends TestCase
{
    public function testATaskReadFromAListHasNoRichDataSummary(): void
    {
        // `data` is populated on single reads only; a list entry must not fake an empty summary.
        $task = Task::fromArray(['id' => 't_1', 'tags' => [], 'status' => 'queued']);

        self::assertNull($task->data);
        self::assertNull($task->title);
        self::assertNull($task->workflowRunId);
        self::assertFalse($task->archived);
    }

    public function testASingleTaskReadExposesItsGeoConstraintsAndRichDataCounts(): void
    {
        $task = Task::fromArray([
            'id' => 't_1',
            'tags' => ['field'],
            'priority' => 90,
            'status' => 'pending',
            'workerId' => 'agent_1',
            'createdAt' => 1750000000000,
            'meta' => ['ticket' => 'T-1'],
            'title' => 'Fix the meter',
            'latitude' => 59.4,
            'longitude' => 24.7,
            'maxDistanceKm' => 25,
            'requireGeo' => true,
            'allowedCidrs' => ['10.0.0.0/8'],
            'workflowRunId' => 'run_1',
            'workflowStepId' => 'collect',
            'data' => [
                'hasContext' => true,
                'referenceCount' => 2,
                'attachmentCount' => 1,
                'commentCount' => 3,
            ],
        ]);

        self::assertSame('Fix the meter', $task->title);
        self::assertSame(59.4, $task->latitude);
        self::assertSame(25.0, $task->maxDistanceKm);
        self::assertTrue($task->requireGeo);
        self::assertSame(['10.0.0.0/8'], $task->allowedCidrs);
        self::assertSame('run_1', $task->workflowRunId);
        self::assertSame('collect', $task->workflowStepId);
        self::assertNotNull($task->data);
        self::assertTrue($task->data->hasContext);
        self::assertSame(2, $task->data->referenceCount);
        self::assertSame(1, $task->data->attachmentCount);
        self::assertSame(3, $task->data->commentCount);
    }

    public function testAnArchivedTaskCarriesItsStoredResult(): void
    {
        $task = Task::fromArray([
            'id' => 't_1', 'tags' => [], 'status' => 'completed',
            'archived' => true, 'result' => ['refunded' => true],
        ]);

        self::assertTrue($task->archived);
        self::assertSame(['refunded' => true], $task->result);
    }

    public function testAWorkerWithNoSkillsConfiguredIsDistinguishableFromOneWithNoneAssigned(): void
    {
        // null means "skills not in use for this worker"; [] means "configured, currently empty".
        self::assertNull(WorkerDetail::fromArray(['id' => 'a', 'skills' => null])->skills);
        self::assertSame([], WorkerDetail::fromArray(['id' => 'a', 'skills' => []])->skills);
    }

    public function testAWorkerDetailReportsItsEffectiveSkillWeights(): void
    {
        $detail = WorkerDetail::fromArray([
            'id' => 'agent_1',
            'tags' => ['english', 'billing'],
            'routingWeights' => ['english' => 100],
            'skills' => [[
                'skillId' => 'skl_1', 'key' => 'refunds', 'name' => 'Refunds',
                'level' => 4, 'weightOverride' => null, 'weight' => 80,
            ]],
            'maxBacklogSize' => 5,
            'available' => false,
            'queueDepth' => 2,
        ]);

        self::assertSame(['english', 'billing'], $detail->tags);
        self::assertSame(['english' => 100], $detail->routingWeights);
        self::assertNotNull($detail->skills);
        self::assertSame(80.0, $detail->skills[0]->weight);
        self::assertNull($detail->skills[0]->weightOverride);
        self::assertSame('refunds', $detail->skills[0]->key);
        self::assertSame(4, $detail->skills[0]->level);
        self::assertFalse($detail->available);
        self::assertSame(2, $detail->queueDepth);
        self::assertSame(5, $detail->maxBacklogSize);
    }

    public function testAWorkerWithNoBacklogCapReportsNullRatherThanZero(): void
    {
        // Zero is a real setting meaning "receive nothing" — it must not collide with "unset".
        self::assertNull(WorkerDetail::fromArray(['id' => 'a', 'maxBacklogSize' => null])->maxBacklogSize);
        self::assertSame(0, WorkerDetail::fromArray(['id' => 'a', 'maxBacklogSize' => 0])->maxBacklogSize);
    }

    public function testLearningStatusBeforeAnyRewardHasNoStatistics(): void
    {
        $status = LearningStatus::fromArray([
            'enabled' => false, 'shadowMode' => true, 'autoWeights' => false,
            'stats' => null, 'modelSize' => 0,
        ]);

        self::assertNull($status->stats);
        self::assertTrue($status->shadowMode);
        self::assertNull($status->signalWeights);
    }

    public function testLearningStatusExposesRewardAveragesOnceRewardsExist(): void
    {
        $status = LearningStatus::fromArray([
            'enabled' => true, 'shadowMode' => false, 'autoWeights' => true,
            'signalWeights' => ['csat' => 1.0], 'rewards' => ['accept' => 1],
            'stats' => [
                'decisions' => 1200, 'rewards' => 800,
                'totalReward' => 940.5, 'averageReward' => 1.175,
            ],
            'modelSize' => 64,
        ]);

        self::assertNotNull($status->stats);
        self::assertSame(1.175, $status->stats->averageReward);
        self::assertSame(1200, $status->stats->decisions);
        self::assertSame(800, $status->stats->rewards);
        self::assertSame(940.5, $status->stats->totalReward);
        self::assertSame(64, $status->modelSize);
    }

    public function testPerWorkerLearningStatsSeparateLearnedWeightsFromConfiguredOnes(): void
    {
        $stats = WorkerLearningStats::fromArray([
            'workerId' => 'agent_1',
            'skills' => [
                [
                    'tag' => 'english', 'count' => 40, 'meanReward' => 1.4,
                    'currentWeight' => 100, 'learnedWeight' => 118, 'skill' => null,
                ],
                [
                    'tag' => 'skill:skl_1', 'count' => 12, 'meanReward' => 0.2,
                    'currentWeight' => null, 'learnedWeight' => 55,
                    'skill' => ['id' => 'skl_1', 'name' => 'Refunds'],
                ],
            ],
        ]);

        self::assertSame(100.0, $stats->skills[0]->currentWeight);
        self::assertSame(118.0, $stats->skills[0]->learnedWeight);
        self::assertSame(40, $stats->skills[0]->count);
        self::assertSame(1.4, $stats->skills[0]->meanReward);
        // A tag with no configured weight is still learnable.
        self::assertNull($stats->skills[1]->currentWeight);
        self::assertSame(['id' => 'skl_1', 'name' => 'Refunds'], $stats->skills[1]->skill);
    }

    public function testAFinishedRunHasNoCurrentStepButKeepsItsHistory(): void
    {
        $run = WorkflowRun::fromArray([
            'id' => 'run_1', 'workflowId' => 'wf_1', 'status' => 'completed',
            'currentStepId' => null, 'currentTaskId' => null,
            'initiatorWorkerId' => 'agent_1', 'context' => [], 'definitionVersion' => 2,
            'history' => [[
                'stepId' => 'collect', 'taskId' => 't_1', 'workerId' => 'agent_1',
                'completedAt' => 1750000500000, 'result' => ['ok' => true],
            ]],
            'parallelBranches' => null, 'createdAt' => 1, 'updatedAt' => 2,
        ]);

        self::assertNull($run->currentStepId);
        self::assertNull($run->parallelBranches);
        self::assertSame(['ok' => true], $run->history[0]->result);
        self::assertSame('agent_1', $run->history[0]->workerId);
        self::assertSame(1750000500000, $run->history[0]->completedAt);
        self::assertSame(2, $run->definitionVersion);
    }

    public function testARunWithParallelBranchesListsEachBranch(): void
    {
        $run = WorkflowRun::fromArray([
            'id' => 'run_1', 'workflowId' => 'wf_1', 'status' => 'active',
            'initiatorWorkerId' => 'agent_1', 'definitionVersion' => 1,
            'createdAt' => 1, 'updatedAt' => 2,
            'parallelBranches' => [
                ['stepId' => 'a', 'assignmentId' => 't_1', 'status' => 'pending'],
                ['stepId' => 'b', 'assignmentId' => 't_2', 'status' => 'completed', 'result' => ['ok' => 1]],
            ],
        ]);

        self::assertNotNull($run->parallelBranches);
        self::assertCount(2, $run->parallelBranches);
        self::assertSame('t_1', $run->parallelBranches[0]->assignmentId);
        self::assertNull($run->parallelBranches[0]->result);
        self::assertSame(['ok' => 1], $run->parallelBranches[1]->result);
    }

    public function testTheStepViewReportsWhichStepIsWaitingOnACallback(): void
    {
        $view = WorkflowRunSteps::fromArray([
            'runId' => 'run_1', 'status' => 'active',
            'steps' => [
                [
                    'stepId' => 'collect', 'name' => 'Collect', 'taskType' => 'assignment',
                    'state' => 'completed', 'taskId' => 't_1', 'workerId' => 'agent_1',
                    'completedAt' => 1, 'result' => ['ok' => true],
                ],
                [
                    'stepId' => 'review', 'name' => 'Review', 'taskType' => 'external',
                    'state' => 'awaiting_callback', 'taskId' => null, 'workerId' => null,
                    'completedAt' => null, 'result' => null,
                ],
            ],
        ]);

        self::assertSame('run_1', $view->runId);
        self::assertSame('completed', $view->steps[0]->state);
        self::assertSame('t_1', $view->steps[0]->taskId);
        self::assertSame(1, $view->steps[0]->completedAt);
        self::assertSame('awaiting_callback', $view->steps[1]->state);
        self::assertNull($view->steps[1]->taskId);
        self::assertNull($view->steps[1]->completedAt);
    }

    public function testATerminalStepReadBackIsDistinguishableFromOneWithNoSuccessor(): void
    {
        $terminal = WorkflowStep::fromArray(['id' => 'a', 'name' => 'A', 'defaultNextStepId' => null]);
        $undeclared = WorkflowStep::fromArray(['id' => 'a', 'name' => 'A']);

        self::assertTrue($terminal->isTerminal());
        self::assertFalse($undeclared->isTerminal());
        // …and re-serialising each preserves the distinction.
        self::assertArrayHasKey('defaultNextStepId', $terminal->toArray());
        self::assertArrayNotHasKey('defaultNextStepId', $undeclared->toArray());
    }

    public function testAStepRoundTripsItsRoutingAndRetryPolicy(): void
    {
        $step = WorkflowStep::fromArray([
            'id' => 'review', 'name' => 'Review', 'taskType' => 'external',
            'external' => ['name' => 'compliance'],
            'routing' => [['condition' => 'result.ok', 'targetStepId' => 'done']],
            'parallelStepIds' => ['a'], 'waitForAll' => true,
            'failurePolicy' => 'retry', 'maxRetries' => 3, 'timeoutMs' => 300000,
            'assignmentTemplate' => ['tags' => ['english']], 'targetUser' => 'initiator',
            'machineTask' => ['handler' => 'h'],
        ]);

        self::assertNotNull($step->getRouting());
        self::assertSame('done', $step->getRouting()[0]->targetStepId);
        self::assertSame('retry', $step->getFailurePolicy());
        self::assertSame(3, $step->getMaxRetries());
        self::assertSame(300000, $step->getTimeoutMs());
        self::assertSame(['a'], $step->getParallelStepIds());
        self::assertTrue($step->getWaitForAll());
        self::assertSame('initiator', $step->getTargetUser());
        self::assertSame(['handler' => 'h'], $step->getMachineTask());
        self::assertSame(['tags' => ['english']], $step->getAssignmentTemplate());
    }

    public function testAWorkflowDefinitionCarriesItsWholeGraph(): void
    {
        $definition = WorkflowDefinition::fromArray([
            'id' => 'wf_1', 'name' => 'Onboarding', 'version' => 2, 'initialStepId' => 'collect',
            'defaultTimeoutMs' => 600000, 'metadata' => ['owner' => 'ops'],
            'steps' => [['id' => 'collect', 'name' => 'Collect']],
        ]);

        self::assertSame(2, $definition->version);
        self::assertSame('collect', $definition->initialStepId);
        self::assertSame(600000, $definition->defaultTimeoutMs);
        self::assertSame(['owner' => 'ops'], $definition->metadata);
        self::assertCount(1, $definition->steps);
    }

    public function testAnEmptyQueueReportsNoOldestWaitRatherThanZero(): void
    {
        // Zero would read as "a task has been waiting 0ms", which is a different fact.
        $stats = WorkspaceStats::fromArray([
            'plan' => 'free', 'tasks' => [], 'workers' => 0,
            'queue' => ['oldestWaitingMs' => null, 'perWorker' => []],
            'meter' => ['period' => '2026-07', 'matchedTasks' => 0, 'includedTasksPerMonth' => 1000],
            'matching' => ['fairness' => 'first-come', 'maxTasksPerWindow' => null, 'windowMs' => null],
        ]);

        self::assertNotNull($stats->queue);
        self::assertNull($stats->queue->oldestWaitingMs);
        self::assertNotNull($stats->matching);
        self::assertSame('first-come', $stats->matching->fairness);
        self::assertNull($stats->matching->maxTasksPerWindow);
    }

    public function testWorkspaceStatsReportPerWorkerLoadAndTheLiveBalancerPolicy(): void
    {
        $stats = WorkspaceStats::fromArray([
            'plan' => 'pro', 'tasks' => ['queued' => 3], 'workers' => 2,
            'queue' => [
                'oldestWaitingMs' => 84000,
                'perWorker' => [
                    ['workerId' => 'agent_1', 'backlog' => 2, 'maxBacklogSize' => 5, 'available' => true],
                    ['workerId' => 'agent_2', 'backlog' => 0, 'maxBacklogSize' => 5, 'available' => false],
                ],
            ],
            'meter' => ['period' => '2026-07', 'matchedTasks' => 421, 'includedTasksPerMonth' => 50000],
            'matching' => ['fairness' => 'balanced', 'maxTasksPerWindow' => 20, 'windowMs' => 3600000],
        ]);

        self::assertNotNull($stats->queue);
        self::assertSame(84000, $stats->queue->oldestWaitingMs);
        self::assertSame('agent_1', $stats->queue->perWorker[0]->workerId);
        self::assertSame(2, $stats->queue->perWorker[0]->backlog);
        self::assertSame(5, $stats->queue->perWorker[0]->maxBacklogSize);
        self::assertTrue($stats->queue->perWorker[0]->available);
        self::assertFalse($stats->queue->perWorker[1]->available);
        self::assertNotNull($stats->matching);
        self::assertSame(20, $stats->matching->maxTasksPerWindow);
        self::assertSame(3600000, $stats->matching->windowMs);
        self::assertSame(421, $stats->meterMatchedTasks);
    }

    public function testStatsFromADataPlaneOnlyDeploymentStillParse(): void
    {
        // queue/matching are absent when the control plane is not attached.
        $stats = WorkspaceStats::fromArray(['plan' => 'free', 'tasks' => [], 'workers' => 0, 'meter' => []]);

        self::assertNull($stats->queue);
        self::assertNull($stats->matching);
    }

    public function testTheTimeseriesReportsEmptyBucketsAsGapsNotZeros(): void
    {
        // An hour with no completions has no average wait — reporting 0ms would be a lie.
        $result = StatsTimeseriesResult::fromArray([
            'from' => 'a', 'to' => 'b', 'bucket' => 'hour',
            'buckets' => [
                [
                    'bucketStart' => 'a', 'completed' => 12, 'cancelled' => 1,
                    'avgWaitMs' => 4200, 'p50WaitMs' => 3000, 'p95WaitMs' => 11000, 'avgHandleMs' => 90000,
                ],
                [
                    'bucketStart' => 'b', 'completed' => 0, 'cancelled' => 0,
                    'avgWaitMs' => null, 'p50WaitMs' => null, 'p95WaitMs' => null, 'avgHandleMs' => null,
                ],
            ],
        ]);

        self::assertSame(11000.0, $result->buckets[0]->p95WaitMs);
        self::assertSame(3000.0, $result->buckets[0]->p50WaitMs);
        self::assertSame(90000.0, $result->buckets[0]->avgHandleMs);
        self::assertSame(12, $result->buckets[0]->completed);
        self::assertSame(1, $result->buckets[0]->cancelled);
        self::assertNull($result->buckets[1]->avgWaitMs);
        self::assertNull($result->buckets[1]->avgHandleMs);
    }

    public function testWorkerProductivityIsReportedPerWorkerForTheWindow(): void
    {
        $result = WorkerStatsResult::fromArray([
            'from' => 'a', 'to' => 'b',
            'workers' => [[
                'workerId' => 'agent_1', 'completed' => 12, 'cancelled' => 1,
                'avgWaitMs' => 4200, 'avgHandleMs' => 90000,
            ]],
        ]);

        self::assertSame('a', $result->from);
        self::assertSame('b', $result->to);
        self::assertSame(12, $result->workers[0]->completed);
        self::assertSame(1, $result->workers[0]->cancelled);
        self::assertSame(4200.0, $result->workers[0]->avgWaitMs);
        self::assertSame(90000.0, $result->workers[0]->avgHandleMs);
    }

    public function testAPendingAttachmentHasNoConfirmationTime(): void
    {
        $created = CreatedAttachment::fromArray([
            'attachment' => [
                'id' => 'att_1', 'taskId' => 't_1', 'filename' => 'a.pdf',
                'contentType' => 'application/pdf', 'sizeBytes' => 10, 'status' => 'pending',
                'uploader' => ['type' => 'api', 'id' => null], 'createdAt' => 1, 'confirmedAt' => null,
            ],
            'upload' => [
                'url' => 'https://s/att_1', 'method' => 'PUT',
                'headers' => ['content-type' => 'application/pdf'], 'expiresAt' => 2,
            ],
        ]);

        self::assertNull($created->attachment->confirmedAt);
        self::assertSame('pending', $created->attachment->status);
        self::assertSame('api', $created->attachment->uploader->type);
        self::assertNull($created->attachment->uploader->id);
        self::assertSame('PUT', $created->upload->method);
        self::assertSame(['content-type' => 'application/pdf'], $created->upload->headers);
        self::assertSame(2, $created->upload->expiresAt);
    }

    public function testAnUploadTargetWithoutAnExplicitMethodDefaultsToPut(): void
    {
        $created = CreatedAttachment::fromArray([
            'attachment' => ['id' => 'att_1'],
            'upload' => ['url' => 'https://s/att_1'],
        ]);

        self::assertSame('PUT', $created->upload->method);
    }

    public function testAConfirmedAttachmentReportsWhenItLanded(): void
    {
        $attachment = Attachment::fromArray(['id' => 'att_1', 'status' => 'ready', 'confirmedAt' => 99]);

        self::assertSame(99, $attachment->confirmedAt);
        self::assertSame('ready', $attachment->status);
    }

    public function testADownloadUrlCarriesItsExpiry(): void
    {
        $download = AttachmentDownload::fromArray(['url' => 'https://s/dl', 'expiresAt' => 5]);

        self::assertSame('https://s/dl', $download->url);
        self::assertSame(5, $download->expiresAt);
    }

    public function testACommentFromTheIntegrationHasNoAuthorIdentity(): void
    {
        $comment = Comment::fromArray([
            'id' => 'c_1', 'taskId' => 't_1',
            'author' => ['type' => 'api', 'id' => null, 'label' => null],
            'body' => 'Escalated', 'createdAt' => 1,
        ]);

        self::assertSame('api', $comment->author->type);
        self::assertNull($comment->author->id);
        self::assertNull($comment->author->label);
        self::assertSame('Escalated', $comment->body);
    }

    public function testACommentPageReportsTheCursorForTheNextPage(): void
    {
        $page = CommentPage::fromArray([
            'comments' => [[
                'id' => 'c_1', 'taskId' => 't_1',
                'author' => ['type' => 'worker', 'id' => 'agent_1', 'label' => 'Ada'],
                'body' => 'Called back', 'createdAt' => 1,
            ]],
            'nextCursor' => 'cursor_c1', 'hasMore' => true,
        ]);

        self::assertTrue($page->hasMore);
        self::assertSame('cursor_c1', $page->nextCursor);
        self::assertSame('Ada', $page->comments[0]->author->label);
    }

    public function testTaskContextWithNothingStoredReadsAsEmptyNotMissing(): void
    {
        $context = TaskContext::fromArray([
            'taskId' => 't_1', 'title' => null, 'description' => null,
            'context' => null, 'references' => [],
        ]);

        self::assertSame([], $context->references);
        self::assertNull($context->title);
        self::assertNull($context->context);
        self::assertNull($context->createdAt);
    }

    public function testTaskContextParsesEachStoredReference(): void
    {
        $context = TaskContext::fromArray([
            'taskId' => 't_1',
            'references' => [[
                'id' => 'ref_1', 'url' => 'https://crm/o/41',
                'label' => 'Order 41', 'contentType' => 'text/html',
            ]],
            'createdAt' => 1, 'updatedAt' => 2,
        ]);

        self::assertSame('ref_1', $context->references[0]->id);
        self::assertSame('Order 41', $context->references[0]->label);
        self::assertSame('text/html', $context->references[0]->contentType);
        self::assertSame(2, $context->updatedAt);
    }

    public function testASkillWithoutADescriptionReadsAsNull(): void
    {
        $skill = Skill::fromArray(['id' => 's', 'key' => 'k', 'name' => 'n', 'description' => null]);

        self::assertNull($skill->description);
        self::assertSame('k', $skill->key);
    }

    public function testSuggestedWorkersExplainWhyEachOneIsOrIsNotEligible(): void
    {
        $result = SuggestWorkersResult::fromArray([
            'tags' => ['english'], 'priority' => 90,
            'workers' => [
                [
                    'workerId' => 'agent_1', 'eligible' => true, 'score' => 180,
                    'effectivePriority' => 90, 'reasons' => [['tag' => 'english']],
                ],
                [
                    'workerId' => 'agent_2', 'eligible' => false, 'score' => 0,
                    'effectivePriority' => 90, 'reasons' => [['vetoed' => true]],
                ],
            ],
        ]);

        self::assertTrue($result->workers[0]->eligible);
        self::assertSame(180.0, $result->workers[0]->score);
        self::assertSame(90.0, $result->workers[0]->effectivePriority);
        self::assertSame([['tag' => 'english']], $result->workers[0]->reasons);
        self::assertFalse($result->workers[1]->eligible);
    }

    public function testTeamPresenceCountsEachAvailabilityState(): void
    {
        $presence = TeamPresence::fromArray([
            'workers' => [
                ['workerId' => 'agent_1', 'label' => 'Ada', 'status' => 'working'],
                [
                    'workerId' => 'agent_2', 'label' => 'Grace', 'status' => 'on-break',
                    'breakStartedAt' => '2026-07-24T11:30:00Z', 'breakReason' => 'lunch',
                ],
                ['workerId' => 'agent_3', 'label' => 'Alan', 'status' => 'paused'],
            ],
            'counts' => ['working' => 1, 'onBreak' => 1, 'paused' => 1, 'total' => 3],
        ]);

        self::assertSame(1, $presence->counts->onBreak);
        self::assertSame(1, $presence->counts->paused);
        self::assertSame(1, $presence->counts->working);
        self::assertSame(3, $presence->counts->total);
        self::assertSame('lunch', $presence->workers[1]->breakReason);
        self::assertNull($presence->workers[0]->breakStartedAt);
    }

    public function testAWorkerNotCurrentlyOnBreakHasNoActiveEntry(): void
    {
        $today = WorkerBreakToday::fromArray([
            'workerId' => 'agent_1', 'since' => 'x',
            'breaks' => [[
                'id' => 'brk_1', 'startedAt' => 'a', 'endedAt' => 'b',
                'reason' => 'lunch', 'durationMs' => 1800000,
            ]],
            'active' => null, 'completedTasks' => 7, 'totalBreakMs' => 1800000,
        ]);

        self::assertNull($today->active);
        self::assertSame('b', $today->breaks[0]->endedAt);
        self::assertSame('lunch', $today->breaks[0]->reason);
        self::assertSame(1800000, $today->breaks[0]->durationMs);
        self::assertSame(7, $today->completedTasks);
    }

    public function testAnOpenBreakAppearsAsTheActiveEntry(): void
    {
        $today = WorkerBreakToday::fromArray([
            'workerId' => 'agent_1', 'since' => 'x', 'breaks' => [],
            'active' => ['id' => 'brk_2', 'startedAt' => 'c', 'endedAt' => null, 'durationMs' => 0],
        ]);

        self::assertNotNull($today->active);
        self::assertSame('brk_2', $today->active->id);
        self::assertNull($today->active->endedAt);
    }

    public function testBreakMetricsRollUpPerWorker(): void
    {
        $metrics = WorkspaceBreakMetrics::fromArray([
            'workers' => [[
                'workerId' => 'agent_2', 'label' => 'Grace', 'count' => 2,
                'totalBreakMs' => 2700000, 'longestBreakMs' => 1800000, 'active' => true,
            ]],
            'totalBreakMs' => 2700000, 'breakCount' => 2, 'activeCount' => 1,
        ]);

        self::assertSame(1, $metrics->activeCount);
        self::assertSame(2, $metrics->breakCount);
        self::assertSame(2700000, $metrics->totalBreakMs);
        self::assertSame('Grace', $metrics->workers[0]->label);
        self::assertSame(1800000, $metrics->workers[0]->longestBreakMs);
        self::assertTrue($metrics->workers[0]->active);
    }

    // ---- quota headers ----

    public function testAResponseWithoutQuotaHeadersYieldsNoSnapshot(): void
    {
        self::assertNull(QuotaInfo::fromHeaders(['content-type' => 'application/json']));
    }

    public function testQuotaHeadersAreReadCaseInsensitively(): void
    {
        $quota = QuotaInfo::fromHeaders([
            'X-Quota-Task-Rate-Limit' => '300',
            'x-quota-workers-remaining' => ['2'],
        ]);

        self::assertNotNull($quota);
        self::assertSame(300, $quota->taskRateLimit);
        self::assertSame(2, $quota->workersRemaining);
        self::assertNull($quota->queuedTasksLimit);
        self::assertTrue($quota->hasAny());
    }

    public function testTheSkillQuotasAreParsedAlongsideTheTaskAndWorkerOnes(): void
    {
        $quota = QuotaInfo::fromHeaders([
            'x-quota-skills-limit' => '50',
            'x-quota-skills-remaining' => '48',
            'x-quota-worker-skills-limit' => '10',
            'x-quota-worker-skills-remaining' => '7',
        ]);

        self::assertNotNull($quota);
        self::assertSame(50, $quota->skillsLimit);
        self::assertSame(48, $quota->skillsRemaining);
        self::assertSame(10, $quota->workerSkillsLimit);
        self::assertSame(7, $quota->workerSkillsRemaining);
    }

    public function testAnEmptyQuotaSnapshotReportsNothingSet(): void
    {
        self::assertFalse((new QuotaInfo())->hasAny());
    }
}
