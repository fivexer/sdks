<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * How much work the background sweeps still owe.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class QueueAuditSweepBacklog
{
    public function __construct(
        public readonly int $scheduleActivations,
        public readonly int $scheduleMisses,
        public readonly int $responseDeadlines,
        public readonly int $completionDeadlines,
        public readonly int $slaExpiries,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduleActivations: (int) ($data['scheduleActivations'] ?? 0),
            scheduleMisses: (int) ($data['scheduleMisses'] ?? 0),
            responseDeadlines: (int) ($data['responseDeadlines'] ?? 0),
            completionDeadlines: (int) ($data['completionDeadlines'] ?? 0),
            slaExpiries: (int) ($data['slaExpiries'] ?? 0),
        );
    }
}
