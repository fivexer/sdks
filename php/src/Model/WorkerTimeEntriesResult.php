<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The recorded shift and break log for one worker over a window — the working-time record an EU
 * employer must keep (CJEU C-55/18).
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTimeEntriesResult
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $from,
        public readonly string $to,
        /** @var list<WorkerTimeEntry> */
        public readonly array $entries,
        public readonly WorkerTimeTotals $totals,
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
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            entries: Json::parseEach($data, 'entries', [WorkerTimeEntry::class, 'fromArray']),
            totals: WorkerTimeTotals::fromArray($data['totals'] ?? []),
        );
    }
}
