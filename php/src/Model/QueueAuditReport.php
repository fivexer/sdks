<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Why the queue is not draining. Walks the queue against the live roster, so it is heavier than the stats snapshot — poll it on a dashboard's cadence, not a request's.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class QueueAuditReport
{
    public function __construct(
        /** Epoch-ms */
        public readonly int $evaluatedAt,
        public readonly int $scanned,
        /** @var list<QueueAuditEntry> */
        public readonly array $entries,
        public readonly QueueAuditSweepBacklog $sweepBacklog,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            evaluatedAt: (int) ($data['evaluatedAt'] ?? 0),
            scanned: (int) ($data['scanned'] ?? 0),
            entries: Json::parseEach($data, 'entries', [QueueAuditEntry::class, 'fromArray']),
            sweepBacklog: QueueAuditSweepBacklog::fromArray($data['sweepBacklog'] ?? []),
        );
    }
}
