<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Outcome of `tasks()->update()`.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class UpdateTaskResult
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly string $id,
        /** Where the task is now — 'queued' when the edit sent it back for rematching */
        public readonly string $status,
        public readonly array $tags,
        public readonly ?float $priority,
        /**
         * The new routing tags no longer reach the worker who held the task, so it was taken
         * back and returned to the queue. The next matching pass offers it to somebody the new
         * tags do reach. Without reading this, a retag that moved work looks like a no-op.
         */
        public readonly bool $requeued = false,
        /** The worker who lost the task, when requeued is true */
        public readonly ?string $previousWorkerId = null,
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
            status: (string) ($data['status'] ?? ''),
            tags: \array_map('strval', $data['tags'] ?? []),
            priority: isset($data['priority']) ? (float) $data['priority'] : null,
            requeued: (bool) ($data['requeued'] ?? false),
            previousWorkerId: isset($data['previousWorkerId']) ? (string) $data['previousWorkerId'] : null,
        );
    }
}
