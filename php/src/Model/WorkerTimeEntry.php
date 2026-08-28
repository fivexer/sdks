<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One recorded stretch of a worker's time — a shift, or a break taken inside one.
 *
 * `endReason` 'timeout' means the platform clocked out an unattended worker that went silent
 * past the liveness contract it declared when going on shift; it is not a judgement about the
 * person.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTimeEntry
{
    public function __construct(
        /** 'shift' or 'break'. */
        public readonly string $type,
        public readonly string $startedAt,
        /** Null while the shift or break is still open. */
        public readonly ?string $endedAt,
        public readonly int $durationMs,
        /** Shifts only: who opened it — 'portal', 'operator' or 'supervisor'. */
        public readonly ?string $source = null,
        /** Shifts only: 'manual', 'timeout' or 'removed'; null while open. */
        public readonly ?string $endReason = null,
        /** Breaks only: the worker's stated reason. */
        public readonly ?string $reason = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? ''),
            startedAt: (string) ($data['startedAt'] ?? ''),
            endedAt: isset($data['endedAt']) ? (string) $data['endedAt'] : null,
            durationMs: (int) ($data['durationMs'] ?? 0),
            source: isset($data['source']) ? (string) $data['source'] : null,
            endReason: isset($data['endReason']) ? (string) $data['endReason'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }
}
