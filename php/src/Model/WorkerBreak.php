<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One break entry, open or closed.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerBreak
{
    public function __construct(
        public readonly string $id,
        public readonly string $startedAt,
        /** null while the break is open */
        public readonly ?string $endedAt,
        public readonly ?string $reason,
        public readonly int $durationMs,
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
            startedAt: (string) ($data['startedAt'] ?? ''),
            endedAt: isset($data['endedAt']) ? (string) $data['endedAt'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            durationMs: (int) ($data['durationMs'] ?? 0),
        );
    }
}
