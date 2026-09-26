<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\CorrectTimeEntryInput;
use Fivexer\SDK\Model\CreateTimeEntryInput;
use Fivexer\SDK\Model\LinkWorkerInput;
use Fivexer\SDK\Model\TeamTimeQuery;
use Fivexer\SDK\Model\TimeEntryBreakInput;

/**
 * The working-time record beyond reading it — recording a missed shift, correcting one, the trail
 * a dispute reads, the team report — plus the offboarding preview, connector links and expiring
 * qualifications.
 */
final class WorkingTimeAndLinksTest extends ClientTestCase
{
    private const CORRECTION = '{"id":"cor_1","entryType":"shift","entryId":"sh_1","workerId":"agent_1",'
        . '"before":{"endedAt":null},"after":{"endedAt":"2026-09-01T17:00:00Z"},"note":"forgot to clock out",'
        . '"by":"ops@example.com","at":"2026-09-02T08:00:00Z"}';

    private const RESULT = '{"entry":{"id":"sh_1","type":"shift","startedAt":"2026-09-01T09:00:00Z",'
        . '"endedAt":"2026-09-01T17:00:00Z","corrected":true,"source":"operator","endReason":"manual",'
        . '"description":"Stocktake","taskId":"task_1"},"correction":' . self::CORRECTION . '}';

    // ---- workers.createTimeEntry ----

    public function testRecordingAMissedShiftSendsBothEndsAndTheReason(): void
    {
        $this->enqueueJson(201, self::RESULT);

        $input = (new CreateTimeEntryInput('2026-09-01T09:00:00Z', '2026-09-01T17:00:00Z', 'paper timesheet'))
            ->breaks([new TimeEntryBreakInput('2026-09-01T12:00:00Z', '2026-09-01T12:30:00Z')])
            ->description('Stocktake')
            ->taskId('task_1');
        $result = $this->client()->workers()->createTimeEntry('agent_1', $input);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/workers/agent_1/time-entries', $this->pathOf($request));
        self::assertSame([
            'startedAt' => '2026-09-01T09:00:00Z',
            'endedAt' => '2026-09-01T17:00:00Z',
            'note' => 'paper timesheet',
            'breaks' => [['startedAt' => '2026-09-01T12:00:00Z', 'endedAt' => '2026-09-01T12:30:00Z']],
            'description' => 'Stocktake',
            'taskId' => 'task_1',
        ], $this->requestBodyJson($request));
        self::assertSame('sh_1', $result->entry->id);
        self::assertTrue($result->entry->corrected);
        self::assertSame('Stocktake', $result->entry->description);
        self::assertSame('task_1', $result->entry->taskId);
        self::assertSame('operator', $result->entry->source);
        self::assertSame('manual', $result->entry->endReason);
        self::assertNull($result->entry->reason);
        self::assertSame('forgot to clock out', $result->correction->note);
        self::assertNull($result->breaks);
        self::assertNull($result->corrections);
    }

    public function testAMinimalRecordedShiftSendsOnlyTheRequiredFields(): void
    {
        $this->enqueueJson(201, self::RESULT);

        $this->client()->workers()->createTimeEntry(
            'agent_1',
            new CreateTimeEntryInput('2026-09-01T09:00:00Z', '2026-09-01T17:00:00Z', 'paper timesheet'),
        );

        self::assertSame(
            ['startedAt' => '2026-09-01T09:00:00Z', 'endedAt' => '2026-09-01T17:00:00Z', 'note' => 'paper timesheet'],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    // ---- workers.correctTimeEntry ----

    public function testACorrectionSendsOnlyWhatChangedPlusTheReason(): void
    {
        $this->enqueueJson(200, self::RESULT);

        $input = (new CorrectTimeEntryInput('forgot to clock out'))->endedAt('2026-09-01T17:00:00Z');
        $result = $this->client()->workers()->correctTimeEntry('agent_1', 'sh/1', $input);

        $request = $this->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('/v1/workers/agent_1/time-entries/sh%2F1', $this->pathOf($request));
        self::assertSame(
            ['note' => 'forgot to clock out', 'endedAt' => '2026-09-01T17:00:00Z'],
            $this->requestBodyJson($request),
        );
        self::assertSame(['endedAt' => null], $result->correction->before);
        self::assertSame(['endedAt' => '2026-09-01T17:00:00Z'], $result->correction->after);
        self::assertSame('ops@example.com', $result->correction->by);
    }

    public function testReopeningAndClearingSendExplicitNulls(): void
    {
        $this->enqueueJson(200, self::RESULT);

        $input = (new CorrectTimeEntryInput('kept working'))->reopen()->clearDescription()->clearTaskId();
        $this->client()->workers()->correctTimeEntry('agent_1', 'sh_1', $input);

        self::assertSame(
            ['note' => 'kept working', 'endedAt' => null, 'description' => null, 'taskId' => null],
            $this->requestBodyJson($this->lastRequest()),
        );
    }

    public function testACorrectionWithBreaksParsesEveryTouchedRecord(): void
    {
        $this->enqueueJson(200, '{"entry":{"id":"sh_1","type":"shift","startedAt":"2026-09-01T08:30:00Z",'
            . '"endedAt":null},"correction":' . self::CORRECTION . ','
            . '"breaks":[{"id":"br_1","type":"break","startedAt":"2026-09-01T12:00:00Z",'
            . '"endedAt":"2026-09-01T12:30:00Z","corrected":true,"reason":"lunch"}],'
            . '"corrections":[' . self::CORRECTION . ',{"id":"cor_2","entryType":"break","entryId":"br_1",'
            . '"workerId":"agent_1","before":null,"after":{"startedAt":"2026-09-01T12:00:00Z"},"note":"n",'
            . '"by":null,"at":"2026-09-02T08:00:00Z"}]}');

        $input = (new CorrectTimeEntryInput('late start'))
            ->reopen()->endedAt('2026-09-01T17:00:00Z')
            ->clearDescription()->description('Inventory')
            ->clearTaskId()->taskId('task_2')
            ->startedAt('2026-09-01T08:30:00Z')
            ->type('shift')
            ->breaks([
                new TimeEntryBreakInput('2026-09-01T12:00:00Z', '2026-09-01T12:30:00Z', 'br_1'),
                new TimeEntryBreakInput('2026-09-01T15:00:00Z', null),
            ]);
        $result = $this->client()->workers()->correctTimeEntry('agent_1', 'sh_1', $input);

        self::assertSame([
            'note' => 'late start',
            'startedAt' => '2026-09-01T08:30:00Z',
            'endedAt' => '2026-09-01T17:00:00Z',
            'type' => 'shift',
            'breaks' => [
                ['id' => 'br_1', 'startedAt' => '2026-09-01T12:00:00Z', 'endedAt' => '2026-09-01T12:30:00Z'],
                // A null end is the statement "still open", so it stays on the wire.
                ['startedAt' => '2026-09-01T15:00:00Z', 'endedAt' => null],
            ],
            'description' => 'Inventory',
            'taskId' => 'task_2',
        ], $this->requestBodyJson($this->lastRequest()));
        self::assertNull($result->entry->endedAt);
        self::assertSame('lunch', $result->breaks[0]->reason ?? null);
        self::assertCount(2, $result->corrections ?? []);
        // Created by this change: there was no row before it, and null says so.
        self::assertNotNull($result->corrections);
        self::assertNull($result->corrections[1]->before);
        self::assertNull($result->corrections[1]->by);
    }

    // ---- workers.timeCorrections ----

    public function testTheCorrectionTrailIsParsed(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","corrections":[' . self::CORRECTION . ']}');

        $trail = $this->client()->workers()->timeCorrections('agent_1');

        self::assertSame('/v1/workers/agent_1/time-corrections', $this->pathOf($this->lastRequest()));
        self::assertCount(1, $trail);
        self::assertSame('shift', $trail[0]->entryType);
        self::assertSame('sh_1', $trail[0]->entryId);
        self::assertSame('agent_1', $trail[0]->workerId);
        self::assertSame('2026-09-02T08:00:00Z', $trail[0]->at);
        self::assertSame('cor_1', $trail[0]->id);
    }

    // ---- workers.offboarding ----

    public function testTheOffboardingPreviewIsParsed(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","lastDay":"2026-09-30",'
            . '"tasks":{"pending":2,"accepted":1,"booked":3},'
            . '"rosters":[{"rosterId":"r_1","name":"October","published":true,"shifts":4}],'
            . '"coverRequests":1,"coverOffers":2,"swaps":0,"pendingTimeOff":1,"leavePolicies":2,"teams":3}');

        $summary = $this->client()->workers()->offboarding('agent_1', '2026-09-30');

        $request = $this->lastRequest();
        self::assertSame('/v1/workers/agent_1/offboarding', $this->pathOf($request));
        self::assertSame('lastDay=2026-09-30', $this->queryOf($request));
        self::assertSame('2026-09-30', $summary->lastDay);
        self::assertSame(2, $summary->tasks->pending);
        self::assertSame(1, $summary->tasks->accepted);
        self::assertSame(3, $summary->tasks->booked);
        self::assertSame('October', $summary->rosters[0]->name);
        self::assertTrue($summary->rosters[0]->published);
        self::assertSame(4, $summary->rosters[0]->shifts);
        self::assertSame('r_1', $summary->rosters[0]->rosterId);
        self::assertSame(1, $summary->coverRequests);
        self::assertSame(2, $summary->coverOffers);
        self::assertSame(0, $summary->swaps);
        self::assertSame(1, $summary->pendingTimeOff);
        self::assertSame(2, $summary->leavePolicies);
        self::assertSame(3, $summary->teams);
    }

    public function testTheOffboardingPreviewWithoutALastDaySendsNoQuery(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","lastDay":"2026-09-26"}');

        $summary = $this->client()->workers()->offboarding('agent_1');

        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertSame(0, $summary->tasks->pending);
        self::assertSame([], $summary->rosters);
    }

    // ---- workers.links / link / unlink ----

    public function testLinksCanBeFilteredByConnector(): void
    {
        $this->enqueueJson(200, '{"links":[{"connector":"hubspot","vendorUserId":"981","workerId":"agent_1",'
            . '"createdAt":"2026-09-01T00:00:00Z"}]}');

        $links = $this->client()->workers()->links('hubspot');

        $request = $this->lastRequest();
        self::assertSame('/v1/workers/links', $this->pathOf($request));
        self::assertSame('connector=hubspot', $this->queryOf($request));
        self::assertSame('981', $links[0]->vendorUserId);
        self::assertSame('hubspot', $links[0]->connector);
        self::assertSame('agent_1', $links[0]->workerId);
        self::assertSame('2026-09-01T00:00:00Z', $links[0]->createdAt);
    }

    public function testListingEveryLinkSendsNoQuery(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->client()->workers()->links());
        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testLinkingPutsTheVendorUserAndTags(): void
    {
        $this->enqueueJson(200, '{"connector":"jira","vendorUserId":"acc-1","workerId":"agent_1",'
            . '"createdAt":"2026-09-01T00:00:00Z"}');

        $link = $this->client()->workers()->link('agent_1', 'jira', (new LinkWorkerInput('acc-1'))->tags(['eng']));

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/v1/workers/agent_1/links/jira', $this->pathOf($request));
        self::assertSame(['vendorUserId' => 'acc-1', 'tags' => ['eng']], $this->requestBodyJson($request));
        self::assertSame('jira', $link->connector);
    }

    public function testLinkingWithoutTagsOmitsThem(): void
    {
        $this->enqueueJson(200, '{"connector":"jira","vendorUserId":"acc-1","workerId":"agent_1"}');

        $this->client()->workers()->link('agent_1', 'jira', new LinkWorkerInput('acc-1'));

        self::assertSame(['vendorUserId' => 'acc-1'], $this->requestBodyJson($this->lastRequest()));
    }

    public function testUnlinkingDeletesTheLink(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->workers()->unlink('agent/1', 'hubspot');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/workers/agent%2F1/links/hubspot', $this->pathOf($request));
    }

    // ---- skills.expiring ----

    public function testExpiringSkillsSendTheWindowAndParseTheRows(): void
    {
        $this->enqueueJson(200, '{"asOf":"2026-09-26","skills":[{"workerId":"agent_1","label":"Mari",'
            . '"skillId":"sk_1","key":"forklift","name":"Forklift","validUntil":"2026-09-20","expired":true}]}');

        $result = $this->client()->skills()->expiring(14, '2026-09-26');

        $request = $this->lastRequest();
        self::assertSame('/v1/skills/expiring', $this->pathOf($request));
        self::assertSame('withinDays=14&asOf=2026-09-26', $this->queryOf($request));
        self::assertSame('2026-09-26', $result->asOf);
        $row = $result->skills[0];
        self::assertTrue($row->expired);
        self::assertSame('Mari', $row->label);
        self::assertSame('forklift', $row->key);
        self::assertSame('Forklift', $row->name);
        self::assertSame('sk_1', $row->skillId);
        self::assertSame('agent_1', $row->workerId);
        self::assertSame('2026-09-20', $row->validUntil);
    }

    public function testExpiringSkillsWithDefaultsSendNoQuery(): void
    {
        $this->enqueueJson(200, '{"asOf":"2026-09-26","skills":[]}');

        self::assertSame([], $this->client()->skills()->expiring()->skills);
        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    // ---- team.time ----

    public function testTheTeamReportSendsItsScopeAndParsesEveryLevel(): void
    {
        $this->enqueueJson(200, '{"from":"2026-09-01T00:00:00Z","to":"2026-09-08T00:00:00Z",'
            . '"workers":[{"workerId":"agent_1","label":"Mari","shiftCount":5,"onShiftMs":144000000,'
            . '"breakCount":5,"breakMs":9000000,"workingMs":135000000,"openShift":true,"openBreak":false,'
            . '"completed":40,"offered":50,"accepted":45,"rejected":3,"expired":2,"failed":1,"released":1,'
            . '"entries":[{"id":"sh_1","type":"shift","startedAt":"2026-09-01T09:00:00Z","endedAt":null,'
            . '"durationMs":0,"description":"Stocktake","taskId":"task_1"}]}],'
            . '"days":[{"day":"2026-09-01","onShiftMs":28800000,"breakMs":1800000,"workingMs":27000000}],'
            . '"totals":{"workerCount":1,"shiftCount":5,"onShiftMs":144000000,"breakCount":5,"breakMs":9000000,'
            . '"workingMs":135000000,"completed":40,"offered":50,"accepted":45,"rejected":3,"expired":2,"failed":1},'
            . '"truncated":true}');

        $query = (new TeamTimeQuery())
            ->from('2026-09-01T00:00:00Z')
            ->to('2026-09-08T00:00:00Z')
            ->teamId('t_1')
            ->workerId('agent_1')
            ->entries();
        $report = $this->client()->team()->time($query);

        $request = $this->lastRequest();
        self::assertSame('/v1/team/time', $this->pathOf($request));
        \parse_str($this->queryOf($request), $params);
        self::assertSame([
            'from' => '2026-09-01T00:00:00Z',
            'to' => '2026-09-08T00:00:00Z',
            'teamId' => 't_1',
            'workerId' => 'agent_1',
            'entries' => 'true',
        ], $params);
        self::assertTrue($report->truncated);
        self::assertSame('2026-09-01T00:00:00Z', $report->from);
        self::assertSame('2026-09-08T00:00:00Z', $report->to);
        $worker = $report->workers[0];
        self::assertSame(135000000, $worker->workingMs);
        self::assertTrue($worker->openShift);
        self::assertFalse($worker->openBreak);
        self::assertSame(
            [5, 144000000, 5, 9000000, 40, 50, 45, 3, 2, 1, 1],
            [
                $worker->shiftCount, $worker->onShiftMs, $worker->breakCount, $worker->breakMs,
                $worker->completed, $worker->offered, $worker->accepted, $worker->rejected,
                $worker->expired, $worker->failed, $worker->released,
            ],
        );
        self::assertSame('Mari', $worker->label);
        self::assertSame('agent_1', $worker->workerId);
        self::assertSame('Stocktake', $worker->entries[0]->description ?? null);
        self::assertSame('task_1', $worker->entries[0]->taskId ?? null);
        self::assertSame('2026-09-01', $report->days[0]->day ?? null);
        self::assertSame(27000000, $report->days[0]->workingMs ?? null);
        self::assertSame(28800000, $report->days[0]->onShiftMs ?? null);
        self::assertSame(1800000, $report->days[0]->breakMs ?? null);
        $totals = $report->totals;
        self::assertSame(
            [1, 5, 144000000, 5, 9000000, 135000000, 40, 50, 45, 3, 2, 1],
            [
                $totals->workerCount, $totals->shiftCount, $totals->onShiftMs, $totals->breakCount,
                $totals->breakMs, $totals->workingMs, $totals->completed, $totals->offered,
                $totals->accepted, $totals->rejected, $totals->expired, $totals->failed,
            ],
        );
    }

    public function testTheTeamReportWithoutEntriesHasNoDaySeries(): void
    {
        $this->enqueueJson(200, '{"from":"a","to":"b","workers":[{"workerId":"agent_1"}],"days":null,'
            . '"totals":{},"truncated":false}');

        $report = $this->client()->team()->time();

        // `entries=false` is never sent: absent already means "no log", and false would be noise.
        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertNull($report->days);
        self::assertNull($report->workers[0]->entries);
        self::assertFalse($report->truncated);
    }

    public function testTurningEntriesBackOffOmitsTheParameter(): void
    {
        $this->enqueueJson(200, '{"workers":[],"totals":{}}');

        $this->client()->team()->time((new TeamTimeQuery())->entries()->entries(false));

        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    // ---- additive fields on existing reads ----

    public function testTodaysMetricsCarryTheOpenShiftStart(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","since":"x","completedTasks":1,"breakCount":0,'
            . '"totalBreakMs":0,"longestBreakMs":0,"workingMs":1,"currentShiftStartedAt":"2026-09-26T08:00:00Z"}');

        self::assertSame('2026-09-26T08:00:00Z', $this->worker()->metricsToday()->currentShiftStartedAt);
    }

    public function testTodaysMetricsOffShiftHaveNoOpenShiftStart(): void
    {
        $this->enqueueJson(200, '{"workerId":"agent_1","currentShiftStartedAt":null}');

        self::assertNull($this->worker()->metricsToday()->currentShiftStartedAt);
    }
}
