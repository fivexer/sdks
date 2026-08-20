<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\Decision;

/**
 * Decision-trace resource (explainability). Delegates to Fivexer::request().
 */
final class Decisions
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * Query decision traces: who was matched, and why.
     *
     * @param string|null $taskId Filter to a single task
     * @param string|null $workerId Filter to a single worker
     * @param int<1, max>|null $limit Max traces to return
     * @return list<Decision> Newest first
     */
    public function list(?string $taskId = null, ?string $workerId = null, ?int $limit = null): array
    {
        $data = $this->client->request('GET', '/decisions', null, [
            'taskId' => $taskId,
            'workerId' => $workerId,
            'limit' => $limit,
        ]) ?? [];
        return \array_map(
            static fn(array $d): Decision => Decision::fromArray($d),
            $data['decisions'] ?? [],
        );
    }
}
