<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Queue depth and per-worker load.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class QueueStats
{
    public function __construct(
        /** null when the queue is empty */
        public readonly ?int $oldestWaitingMs,
        /** @var list<WorkerLoad> */
        public readonly array $perWorker,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            oldestWaitingMs: isset($data['oldestWaitingMs']) ? (int) $data['oldestWaitingMs'] : null,
            perWorker: Json::parseEach($data, 'perWorker', [WorkerLoad::class, 'fromArray']),
        );
    }
}
