<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;

/**
 * Recurring templates: the standing tasks occurrences are cut from.
 *
 * A template is created through tasks()->create() with a recurrence — there is no separate
 * create — and the only two operations against it are listing and stopping. It is never itself
 * matchable and never appears in tasks()->list(), tasks()->scheduled() or the queue stats, so a
 * client showing someone their standing work has to read this collection rather than the task
 * list.
 */
final class RecurringTemplatesTest extends ClientTestCase
{
    private const TEMPLATES = '{"recurring":['
        . '{"id":"nightly-sweep","tags":["ops"],"priority":80,"title":"Nightly sweep",'
        . '"recurrence":{"everyMs":86400000,"windowMs":3600000,"onMiss":"park","catchUp":"skip"},'
        . '"nextAt":1756080000000,"occurrences":12},'
        . '{"id":"hourly-ping","tags":["ops","monitoring"],"priority":null,"title":null,'
        . '"recurrence":{"everyMs":3600000},"nextAt":1756003600000,"occurrences":240}'
        . '],"count":2}';

    public function testListingReadsTemplatesWithTheirClocks(): void
    {
        $this->enqueueJson(200, self::TEMPLATES);

        $templates = $this->client()->tasks()->recurring()->list();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/v1/tasks/recurring', $this->pathOf($request));
        self::assertCount(2, $templates);
        self::assertSame('nightly-sweep', $templates[0]->id);
        self::assertSame(86400000, $templates[0]->recurrence->everyMs);
        self::assertSame('park', $templates[0]->recurrence->getOnMiss());
        // The clock is the point of the read: when the next occurrence opens, how many have run.
        self::assertSame(1756080000000, $templates[0]->nextAt);
        self::assertSame(12, $templates[0]->occurrences);
    }

    public function testATemplateWithoutAPriorityOrTitleReadsThemAsNull(): void
    {
        $this->enqueueJson(200, self::TEMPLATES);

        $template = $this->client()->tasks()->recurring()->list()[1];

        self::assertNull($template->priority);
        self::assertNull($template->title);
        self::assertNull($template->recurrence->getCatchUp());
    }

    public function testAnEmptyCollectionReadsAsAnEmptyListNotANull(): void
    {
        $this->enqueueJson(200, '{"count":0}');

        // A workspace with no templates must not make a caller null-check before iterating.
        self::assertSame([], $this->client()->tasks()->recurring()->list());
    }

    public function testRemovingATemplateLeavesItsOccurrencesAloneByDefault(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->recurring()->remove('nightly-sweep');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/tasks/recurring/nightly-sweep', $this->pathOf($request));
        // Occurrences already cut are real scheduled tasks someone may be about to work. A
        // dropScheduled=false on the wire would look like a caller who considered them and
        // declined; sending nothing says they never asked, which is the truth.
        self::assertSame('', $this->queryOf($request));
    }

    public function testRemovingCanAlsoDropTheOccurrencesAlreadyMaterialized(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->recurring()->remove('nightly-sweep', true);

        self::assertSame('dropScheduled=true', $this->queryOf($this->lastRequest()));
    }

    public function testATemplateIdWithASlashCannotEscapeThePath(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->recurring()->remove('tenant/nightly');

        self::assertSame('/v1/tasks/recurring/tenant%2Fnightly', $this->pathOf($this->lastRequest()));
    }

    public function testRemovingAnUnknownTemplateRaisesRatherThanSucceedingQuietly(): void
    {
        $this->enqueueJson(404, '{"error":{"code":"not_found","message":"recurring task"}}');

        try {
            $this->client()->tasks()->recurring()->remove('gone');
            self::fail('expected a FivexerApiException');
        } catch (FivexerApiException $error) {
            // Removing a template twice is not idempotent; swallowing the 404 masks a wrong id.
            self::assertSame(404, $error->statusCode);
            self::assertSame('not_found', $error->apiCode);
        }
    }
}
