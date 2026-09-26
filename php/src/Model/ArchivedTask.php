<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One finished task, as history records it.
 *
 * Distinct from {@see Task} because these are different facts: a live task has a queue position
 * and a deadline, a finished one has an outcome and the times it passed through. `title` is the
 * title the task finished under, kept after the live record is deleted. Timestamps are
 * epoch-milliseconds.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class ArchivedTask
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed>|null $meta
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        public readonly string $id,
        public readonly array $tags,
        public readonly ?float $priority,
        /** 'completed', 'cancelled', 'failed' or 'expired' */
        public readonly string $status,
        public readonly ?string $workerId,
        public readonly ?int $createdAt,
        /** When it was matched to its worker; null if it never was (cancelled while queued) */
        public readonly ?int $matchedAt,
        /** When it reached its terminal state */
        public readonly int $terminalAt,
        public readonly ?array $meta = null,
        public readonly ?string $title = null,
        public readonly ?array $result = null,
        public readonly ?TaskDataSummary $data = null,
        /** Always true — the marker that tells an archived read from a live one */
        public readonly bool $archived = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            tags: \array_map('strval', $data['tags'] ?? []),
            priority: isset($data['priority']) ? (float) $data['priority'] : null,
            status: (string) ($data['status'] ?? ''),
            workerId: isset($data['workerId']) ? (string) $data['workerId'] : null,
            createdAt: isset($data['createdAt']) ? (int) $data['createdAt'] : null,
            matchedAt: isset($data['matchedAt']) ? (int) $data['matchedAt'] : null,
            terminalAt: (int) ($data['terminalAt'] ?? 0),
            meta: $data['meta'] ?? null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            result: $data['result'] ?? null,
            data: isset($data['data']) && \is_array($data['data']) ? TaskDataSummary::fromArray($data['data']) : null,
            archived: (bool) ($data['archived'] ?? true),
        );
    }
}
