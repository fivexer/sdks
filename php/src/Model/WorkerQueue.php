<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** Result of GET /v1/workers/{id}/queue - a worker's current task ids. */
final class WorkerQueue
{
    /** @param list<string> $taskIds */
    public function __construct(
        public readonly string $workerId,
        public readonly array $taskIds,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            taskIds: \array_map('strval', $data['taskIds'] ?? []),
        );
    }
}
