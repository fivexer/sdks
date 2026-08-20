<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One queued task and what is stopping it being matched.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class QueueAuditEntry
{
    public function __construct(
        public readonly string $taskId,
        /** @var list<string> */
        public readonly array $tags,
        /** Null when the task was never queued. */
        public readonly ?int $waitingMs,
        public readonly int $eligibleWorkerCount,
        /** @var list<string> */
        public readonly array $uncoveredTags,
        /** @var array<string, int> */
        public readonly array $blockers,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: (string) ($data['taskId'] ?? ''),
            tags: \array_values(\array_map(strval(...), $data['tags'] ?? [])),
            waitingMs: isset($data['waitingMs']) ? (int) $data['waitingMs'] : null,
            eligibleWorkerCount: (int) ($data['eligibleWorkerCount'] ?? 0),
            uncoveredTags: \array_values(\array_map(strval(...), $data['uncoveredTags'] ?? [])),
            blockers: \array_map(intval(...), $data['blockers'] ?? []),
        );
    }
}
