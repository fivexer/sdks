<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Worked time, breaks and outcomes per worker and per day for a window — the team working-time
 * report. Days are UTC.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamTimeResult
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        /** @var list<TeamTimeWorker> */
        public readonly array $workers,
        /**
         * Null unless the query asked for entries — the series is derived from the log.
         *
         * @var list<TeamTimeDay>|null
         */
        public readonly ?array $days,
        public readonly TeamTimeTotals $totals,
        /** The window held more log rows than one read returns; the record is incomplete */
        public readonly bool $truncated = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        /** @var list<TeamTimeDay>|null $days */
        $days = Json::parseEachOrNull($data, 'days', [TeamTimeDay::class, 'fromArray']);
        return new self(
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            workers: Json::parseEach($data, 'workers', [TeamTimeWorker::class, 'fromArray']),
            days: $days,
            totals: TeamTimeTotals::fromArray($data['totals'] ?? []),
            truncated: (bool) ($data['truncated'] ?? false),
        );
    }
}
