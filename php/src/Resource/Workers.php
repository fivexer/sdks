<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\PatchWorker;
use Fivexer\SDK\Model\UpsertWorker;
use Fivexer\SDK\Model\WorkerAvailability;
use Fivexer\SDK\Model\WorkerDetail;
use Fivexer\SDK\Model\WorkerList;
use Fivexer\SDK\Model\WorkerMetrics;
use Fivexer\SDK\Model\WorkerQueue;
use Fivexer\SDK\Model\WorkerTimeEntriesResult;

/**
 * Worker management resource. Delegates to Fivexer::request() so all transport
 * logic (auth, retry, quota, errors) lives in one place.
 */
final class Workers
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** Create or update a worker; returns the worker id (server-assigned if absent). */
    public function upsert(?UpsertWorker $worker = null): string
    {
        $data = $this->client->request('POST', '/workers', ($worker ?? new UpsertWorker())->toArray()) ?? [];
        return (string) $data['id'];
    }

    /** List all worker ids in the workspace. */
    public function list(): WorkerList
    {
        return WorkerList::fromArray($this->client->request('GET', '/workers') ?? []);
    }

    /** A worker's current task queue (tentative matches). */
    public function queue(string $workerId): WorkerQueue
    {
        return WorkerQueue::fromArray(
            $this->client->request('GET', '/workers/' . \rawurlencode($workerId) . '/queue') ?? [],
        );
    }

    /**
     * The operator-plane mirror of the worker's own portal metrics: today plus a rolling window.
     * Worked time comes from the shift log — shift time minus overlapping breaks — so both planes
     * report the same figure. Requires the control plane.
     *
     * @param string|null $window rolling window such as '7d' (the default) up to '30d', or null
     */
    public function metrics(string $workerId, ?string $window = null): WorkerMetrics
    {
        return WorkerMetrics::fromArray(
            $this->client->request(
                'GET',
                '/workers/' . \rawurlencode($workerId) . '/metrics',
                null,
                ['window' => $window],
            ) ?? []
        );
    }

    /**
     * The recorded shift and break log for a window (default: the last 7 days) — the working-time
     * record an EU employer must keep (CJEU C-55/18). A shift is a recorded stretch of
     * availability and breaks are time inside one, so `totals->workingMs` is `onShiftMs` minus
     * `breakMs`. Requires the control plane.
     *
     * @param string|null $from ISO-8601 start, or null
     * @param string|null $to ISO-8601 end, or null
     */
    public function timeEntries(
        string $workerId,
        ?string $from = null,
        ?string $to = null,
    ): WorkerTimeEntriesResult {
        return WorkerTimeEntriesResult::fromArray(
            $this->client->request(
                'GET',
                '/workers/' . \rawurlencode($workerId) . '/time-entries',
                null,
                ['from' => $from, 'to' => $to],
            ) ?? []
        );
    }

    /** Remove a worker and clear their backlog. */
    public function remove(string $workerId): void
    {
        $this->client->request('DELETE', '/workers/' . \rawurlencode($workerId));
    }

    /** Full detail of one worker, including their current load against their cap. */
    public function get(string $workerId): WorkerDetail
    {
        return WorkerDetail::fromArray(
            $this->client->request('GET', '/workers/' . \rawurlencode($workerId)) ?? []
        );
    }

    /** Partial update — fields left unset keep their stored value. Returns the worker id. */
    public function patch(string $workerId, PatchWorker $patch): string
    {
        $data = $this->client->request('PATCH', '/workers/' . \rawurlencode($workerId), $patch->toArray()) ?? [];
        return (string) $data['id'];
    }

    /**
     * Pause or resume a worker. Pausing preserves the unaccepted backlog ("back in ten
     * minutes").
     *
     * @param bool $releaseBacklog When pausing, also requeue the unaccepted backlog so others
     *     inherit it now ("gone for the day"). Accepted work in progress is never touched.
     *     Only valid when pausing — the API rejects it on a resume, so it is sent only when true.
     */
    public function setAvailability(
        string $workerId,
        bool $available,
        bool $releaseBacklog = false,
    ): WorkerAvailability {
        $body = ['available' => $available];
        if ($releaseBacklog) {
            $body['releaseBacklog'] = true;
        }
        return WorkerAvailability::fromArray(
            $this->client->request('POST', '/workers/' . \rawurlencode($workerId) . '/availability', $body) ?? []
        );
    }
}
