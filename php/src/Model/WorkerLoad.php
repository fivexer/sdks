<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker backlog against their cap.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerLoad
{
    public function __construct(
        public readonly string $workerId,
        public readonly int $backlog,
        public readonly int $maxBacklogSize,
        public readonly bool $available,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            backlog: (int) ($data['backlog'] ?? 0),
            maxBacklogSize: (int) ($data['maxBacklogSize'] ?? 0),
            available: (bool) ($data['available'] ?? false),
        );
    }
}
