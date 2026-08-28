<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\RecurringTask;

/**
 * Standing templates that occurrences are cut from.
 *
 * A template is created through {@see Tasks::create()} with a recurrence — there is no separate
 * create here. It is never itself matchable and never appears in {@see Tasks::list()},
 * {@see Tasks::scheduled()} or the queue stats; only the occurrences cut from it do, so a caller
 * showing someone their standing work has to read this collection rather than the task list.
 */
final class TaskRecurring
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * Templates with their clocks, soonest next occurrence first.
     *
     * @return list<RecurringTask>
     */
    public function list(): array
    {
        $data = $this->client->request('GET', '/tasks/recurring') ?? [];
        /** @var list<RecurringTask> */
        return Json::parseEach($data, 'recurring', [RecurringTask::class, 'fromArray']);
    }

    /**
     * Stop a template. Occurrences already materialized live on unless $dropScheduled.
     *
     * Throws a FivexerApiException with a 404 on an unknown id rather than succeeding quietly —
     * removing a template twice is not idempotent here, and swallowing the 404 would mask a
     * wrong id.
     *
     * @param bool $dropScheduled Also discard the occurrences already cut from it. Sent only
     *     when true: those occurrences are real scheduled tasks someone may be about to work,
     *     and a `false` on the wire would look like a caller who considered them and declined.
     */
    public function remove(string $templateId, bool $dropScheduled = false): void
    {
        $this->client->request(
            'DELETE',
            '/tasks/recurring/' . \rawurlencode($templateId),
            null,
            $dropScheduled ? ['dropScheduled' => 'true'] : []
        );
    }
}
