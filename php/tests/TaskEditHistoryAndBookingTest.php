<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\TaskHistoryQuery;
use Fivexer\SDK\Model\TaskReferenceInput;
use Fivexer\SDK\Model\TimeSlot;
use Fivexer\SDK\Model\UpdateTaskInput;

/**
 * Editing a live task, reading the finished-task archive, and the appointment surface: slots on
 * a task, the planner's booking calendar, and the per-task booking override.
 */
final class TaskEditHistoryAndBookingTest extends ClientTestCase
{
    private const BOOKING = '{"taskId":"task_1","workerId":"agent_1","startAt":1750000000000,'
        . '"endAt":1750003600000,"bookedAt":1749900000000,"source":"sweep",'
        . '"warnings":[{"from":1750000000000,"to":1750001800000,"reason":"outside-shift"}]}';

    // ---- tasks.update ----

    public function testAnUpdateSendsOnlyTheFieldsThatWereSet(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","status":"pending","tags":["billing"],"priority":7,'
            . '"requeued":false,"previousWorkerId":null}');

        $result = $this->client()->tasks()->update('task_1', (new UpdateTaskInput())->title('Refund, urgent'));

        $request = $this->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('/v1/tasks/task_1', $this->pathOf($request));
        // Patch semantics: anything not mentioned must stay off the wire, or it would be cleared.
        self::assertSame(['title' => 'Refund, urgent'], $this->requestBodyJson($request));
        self::assertSame('pending', $result->status);
        self::assertSame(7.0, $result->priority);
        self::assertFalse($result->requeued);
        self::assertNull($result->previousWorkerId);
    }

    public function testClearingAFieldSendsAnExplicitNull(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","status":"queued","tags":["a"],"priority":null}');

        $input = (new UpdateTaskInput())
            ->clearTitle()
            ->clearDescription()
            ->clearContext()
            ->clearReferences()
            ->clearMeta();
        $this->client()->tasks()->update('task_1', $input);

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame(
            ['title' => null, 'description' => null, 'context' => null, 'references' => null, 'meta' => null],
            $body,
        );
    }

    public function testSettingAFieldAfterClearingItKeepsTheLaterCall(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","status":"queued","tags":["a"]}');

        $input = (new UpdateTaskInput())
            ->clearTitle()->title('Back again')
            ->clearDescription()->description('Longer text')
            ->clearContext()->context(['orderId' => '41'])
            ->clearReferences()->references([new TaskReferenceInput('https://crm/o/41')])
            ->clearMeta()->meta(['source' => 'crm'])
            ->tags(['billing', 'vip'])
            ->priority(9);
        $this->client()->tasks()->update('task_1', $input);

        self::assertSame([
            'tags' => ['billing', 'vip'],
            'priority' => 9.0,
            'title' => 'Back again',
            'description' => 'Longer text',
            'context' => ['orderId' => '41'],
            'references' => [['url' => 'https://crm/o/41']],
            'meta' => ['source' => 'crm'],
        ], $this->requestBodyJson($this->lastRequest()));
    }

    public function testARetagThatTookTheTaskOffItsHolderSaysSo(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","status":"queued","tags":["spanish"],"priority":5,'
            . '"requeued":true,"previousWorkerId":"agent_7"}');

        $result = $this->client()->tasks()->update('task_1', (new UpdateTaskInput())->tags(['spanish']));

        // Without this a retag that moved work off somebody would read as a silent no-op.
        self::assertTrue($result->requeued);
        self::assertSame('agent_7', $result->previousWorkerId);
        self::assertSame(['spanish'], $result->tags);
    }

    // ---- tasks.history ----

    public function testHistoryWithNoFilterSendsNoQuery(): void
    {
        $this->enqueueJson(200, '{"tasks":[],"nextCursor":null,"hasMore":false}');

        $page = $this->client()->tasks()->history();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/v1/tasks/history', $this->pathOf($request));
        self::assertSame('', $this->queryOf($request));
        self::assertSame([], $page->tasks);
        self::assertNull($page->nextCursor);
        self::assertFalse($page->hasMore);
    }

    public function testSeveralStatusesTravelAsOneCommaJoinedParameter(): void
    {
        $this->enqueueJson(200, '{"tasks":[],"hasMore":false}');

        $query = (new TaskHistoryQuery())
            ->status('failed', 'expired')
            ->workerId('agent_1')
            ->tag('billing')
            ->from('2026-09-01T00:00:00Z')
            ->to('2026-09-08T00:00:00Z')
            ->q('refund')
            ->cursor('c_2')
            ->limit(25);
        $this->client()->tasks()->history($query);

        \parse_str($this->queryOf($this->lastRequest()), $params);
        self::assertSame([
            'status' => 'failed,expired',
            'workerId' => 'agent_1',
            'tag' => 'billing',
            'from' => '2026-09-01T00:00:00Z',
            'to' => '2026-09-08T00:00:00Z',
            'q' => 'refund',
            'cursor' => 'c_2',
            'limit' => '25',
        ], $params);
    }

    public function testAnEmptyStatusListOmitsTheParameter(): void
    {
        $this->enqueueJson(200, '{"tasks":[],"hasMore":false}');

        $this->client()->tasks()->history(new TaskHistoryQuery(status: []));

        self::assertSame('', $this->queryOf($this->lastRequest()));
    }

    public function testHistoryParsesArchivedTasks(): void
    {
        $this->enqueueJson(200, '{"tasks":['
            . '{"id":"task_1","tags":["billing"],"priority":5,"status":"completed","workerId":"agent_1",'
            . '"createdAt":1750000000000,"matchedAt":1750000100000,"terminalAt":1750000900000,'
            . '"meta":{"k":"v"},"title":"Refund","result":{"ok":true},'
            . '"data":{"hasContext":true,"referenceCount":1,"attachmentCount":0,"commentCount":2},"archived":true},'
            . '{"id":"task_2","tags":[],"priority":null,"status":"cancelled","workerId":null,"createdAt":null,'
            . '"matchedAt":null,"terminalAt":1750000500000,"meta":null,"title":null,"result":null,"data":null,'
            . '"archived":true}'
            . '],"nextCursor":"c_3","hasMore":true}');

        $page = $this->client()->tasks()->history();

        self::assertSame('c_3', $page->nextCursor);
        self::assertTrue($page->hasMore);
        $done = $page->tasks[0];
        self::assertSame('completed', $done->status);
        self::assertSame(5.0, $done->priority);
        self::assertSame('agent_1', $done->workerId);
        self::assertSame(1750000100000, $done->matchedAt);
        self::assertSame(1750000900000, $done->terminalAt);
        self::assertSame(['ok' => true], $done->result);
        self::assertSame('Refund', $done->title);
        self::assertSame(2, $done->data?->commentCount);
        self::assertTrue($done->archived);
        $cancelled = $page->tasks[1];
        // Cancelled while queued: it was never matched, and null says so rather than a zero.
        self::assertNull($cancelled->matchedAt);
        self::assertNull($cancelled->workerId);
        self::assertNull($cancelled->createdAt);
        self::assertNull($cancelled->priority);
        self::assertNull($cancelled->data);
    }

    // ---- slots on create and read ----

    public function testCreatingASlottedTaskSendsTheAppointment(): void
    {
        $this->enqueueJson(201, '{"id":"task_1","tags":["plumber"],"status":"scheduled"}');

        $slot = (new TimeSlot(1750000000000, 3600000))->bookAheadMs(86400000)->onUnbooked('park');
        $this->client()->tasks()->create((new CreateTask(['plumber']))->slot($slot));

        self::assertSame(
            ['startAt' => 1750000000000, 'durationMs' => 3600000, 'bookAheadMs' => 86400000, 'onUnbooked' => 'park'],
            $this->requestBodyJson($this->lastRequest())['slot'],
        );
    }

    public function testASlotWithOnlyItsRequiredFieldsOmitsTheRest(): void
    {
        $this->enqueueJson(201, '{"id":"task_1","tags":["plumber"],"status":"scheduled"}');

        $this->client()->tasks()->create((new CreateTask(['plumber']))->slot(new TimeSlot(1750000000000, 60000)));

        self::assertSame(
            ['startAt' => 1750000000000, 'durationMs' => 60000],
            $this->requestBodyJson($this->lastRequest())['slot'],
        );
    }

    public function testATaskReadCarriesItsSlotAndBooking(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","tags":["plumber"],"status":"scheduled",'
            . '"slot":{"startAt":1750000000000,"durationMs":3600000,"bookAheadMs":604800000,"onUnbooked":"queue"},'
            . '"booking":' . self::BOOKING . '}');

        $task = $this->client()->tasks()->get('task_1');

        self::assertSame(1750000000000, $task->slot?->getStartAt());
        self::assertSame(3600000, $task->slot?->getDurationMs());
        self::assertSame(604800000, $task->slot?->getBookAheadMs());
        self::assertSame('queue', $task->slot?->getOnUnbooked());
        self::assertSame('agent_1', $task->booking?->workerId);
        self::assertSame('sweep', $task->booking?->source);
    }

    public function testAnUnbookedSlotReadsAsANullBooking(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","tags":["plumber"],"status":"scheduled",'
            . '"slot":{"startAt":1750000000000,"durationMs":3600000},"booking":null}');

        $task = $this->client()->tasks()->get('task_1');

        // Null is a real answer on a slotted task — the one a planner acts on.
        self::assertNull($task->booking);
        self::assertNull($task->slot?->getBookAheadMs());
        self::assertNull($task->slot?->getOnUnbooked());
    }

    // ---- tasks.bookings ----

    public function testTheBookingCalendarSendsTheWindowAndParsesBookings(): void
    {
        $this->enqueueJson(200, '{"bookings":[' . self::BOOKING . '],"count":1}');

        $bookings = $this->client()->tasks()->bookings(1750000000000, 1750600000000, 'agent_1');

        $request = $this->lastRequest();
        self::assertSame('/v1/tasks/bookings', $this->pathOf($request));
        self::assertSame('from=1750000000000&to=1750600000000&workerId=agent_1', $this->queryOf($request));
        self::assertCount(1, $bookings);
        self::assertSame('task_1', $bookings[0]->taskId);
        self::assertSame(1750003600000, $bookings[0]->endAt);
        self::assertSame(1749900000000, $bookings[0]->bookedAt);
        self::assertSame('outside-shift', $bookings[0]->warnings[0]->reason);
        self::assertSame(1750001800000, $bookings[0]->warnings[0]->to);
    }

    public function testTheCalendarForEveryoneOmitsTheWorker(): void
    {
        $this->enqueueJson(200, '{"count":0}');

        $bookings = $this->client()->tasks()->bookings(1, 2);

        self::assertSame('from=1&to=2', $this->queryOf($this->lastRequest()));
        self::assertSame([], $bookings);
    }

    // ---- tasks.booking ----

    public function testCandidatesAreParsedWithWhatBlocksThem(): void
    {
        $this->enqueueJson(200, '{"candidates":['
            . '{"workerId":"agent_1","score":0.9,"effectivePriority":12.5,"bookable":true,'
            . '"reasons":[{"kind":"skill","ok":true}]},'
            . '{"workerId":"agent_2","score":0.4,"effectivePriority":6,"bookable":false,"reasons":[],'
            . '"clashingTaskId":"task_9","blocked":[{"from":1,"to":2,"reason":"leave"}],'
            . '"warnings":[{"from":3,"to":4,"reason":"calendar"}]}'
            . '],"count":2}');

        $candidates = $this->client()->tasks()->booking()->candidates('task_1');

        self::assertSame('/v1/tasks/task_1/booking/candidates', $this->pathOf($this->lastRequest()));
        self::assertTrue($candidates[0]->bookable);
        self::assertSame(12.5, $candidates[0]->effectivePriority);
        self::assertSame([['kind' => 'skill', 'ok' => true]], $candidates[0]->reasons);
        self::assertNull($candidates[0]->clashingTaskId);
        self::assertSame([], $candidates[0]->blocked);
        self::assertFalse($candidates[1]->bookable);
        self::assertSame(0.4, $candidates[1]->score);
        self::assertSame('task_9', $candidates[1]->clashingTaskId);
        self::assertSame('leave', $candidates[1]->blocked[0]->reason);
        self::assertSame('calendar', $candidates[1]->warnings[0]->reason);
    }

    public function testBookingSendsTheWorkerAndReturnsTheBooking(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","booking":' . self::BOOKING . '}');

        $booking = $this->client()->tasks()->booking()->set('task_1', 'agent_1');

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/tasks/task_1/booking', $this->pathOf($request));
        // `force` is sent only when asked for — a false on the wire reads as a considered choice.
        self::assertSame(['workerId' => 'agent_1'], $this->requestBodyJson($request));
        self::assertSame('agent_1', $booking->workerId);
    }

    public function testForcingABookingSendsTheFlag(): void
    {
        $this->enqueueJson(200, '{"id":"task_1","booking":' . self::BOOKING . '}');

        $this->client()->tasks()->booking()->set('task_1', 'agent_1', true);

        self::assertSame(['workerId' => 'agent_1', 'force' => true], $this->requestBodyJson($this->lastRequest()));
    }

    public function testAClashRaisesTheConflict(): void
    {
        $this->enqueueJson(409, '{"error":{"code":"booking_clash","message":"clash"}}');

        try {
            $this->client()->tasks()->booking()->set('task_1', 'agent_1');
            self::fail('expected a FivexerApiException');
        } catch (FivexerApiException $error) {
            self::assertSame(409, $error->statusCode);
        }
    }

    public function testReleasingABookingDeletesIt(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->booking()->release('tenant/task_1');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/tasks/tenant%2Ftask_1/booking', $this->pathOf($request));
    }
}
