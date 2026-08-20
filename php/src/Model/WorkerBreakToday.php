<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A worker own breaks and completions for today.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerBreakToday
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $since,
        /** @var list<WorkerBreak> */
        public readonly array $breaks,
        /** the open break, if one is running */
        public readonly ?WorkerBreak $active,
        public readonly int $completedTasks,
        public readonly int $totalBreakMs,
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
            since: (string) ($data['since'] ?? ''),
            breaks: Json::parseEach($data, 'breaks', [WorkerBreak::class, 'fromArray']),
            active: isset($data['active']) ? WorkerBreak::fromArray($data['active']) : null,
            completedTasks: (int) ($data['completedTasks'] ?? 0),
            totalBreakMs: (int) ($data['totalBreakMs'] ?? 0),
        );
    }
}
