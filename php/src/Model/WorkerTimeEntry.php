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
        /**
         * The row's own id — what a correction addresses. A wrong figure is disputed by row,
         * never by its times, which are the thing in dispute.
         */
        public readonly string $id,
        /** 'shift' or 'break'. */
        public readonly string $type,
        public readonly string $startedAt,
        /** Null while the shift or break is still open. */
        public readonly ?string $endedAt,
        public readonly int $durationMs,
        /** The times were changed after the fact; the correction says by whom and why. */
        public readonly bool $corrected = false,
        /**
         * Why it was changed, and who changed it. Carried on the per-worker log and on the
         * worker's own portal view — a person can always read the reason for a change to their
         * own record — but not on the team board, which lists many people.
         */
        public readonly ?string $correctionNote = null,
        public readonly ?string $correctedBy = null,
        /**
         * Whether the record was changed or stated from nothing: an added shift
         * was never adjusted, and saying so sends the worker looking for an
         * original that never existed. Portal view only.
         */
        public readonly ?string $correctionKind = null,
        public readonly ?string $correctedAt = null,
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
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            startedAt: (string) ($data['startedAt'] ?? ''),
            endedAt: isset($data['endedAt']) ? (string) $data['endedAt'] : null,
            durationMs: (int) ($data['durationMs'] ?? 0),
            corrected: (bool) ($data['corrected'] ?? false),
            correctionNote: isset($data['correctionNote']) ? (string) $data['correctionNote'] : null,
            correctedBy: isset($data['correctedBy']) ? (string) $data['correctedBy'] : null,
            correctionKind: isset($data['correctionKind']) ? (string) $data['correctionKind'] : null,
            correctedAt: isset($data['correctedAt']) ? (string) $data['correctedAt'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            endReason: isset($data['endReason']) ? (string) $data['endReason'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }
}
