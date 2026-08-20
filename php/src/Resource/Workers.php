<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\PatchWorker;
use Fivexer\SDK\Model\UpsertWorker;
use Fivexer\SDK\Model\WorkerAvailability;
use Fivexer\SDK\Model\WorkerDetail;
use Fivexer\SDK\Model\WorkerList;
use Fivexer\SDK\Model\WorkerQueue;

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
