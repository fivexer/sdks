<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A shift or break as it stands after a correction.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class CorrectedTimeEntry
{
    public function __construct(
        public readonly string $id,
        /** 'shift' or 'break' */
        public readonly string $type,
        public readonly string $startedAt,
        /** Null when the correction left or reopened it */
        public readonly ?string $endedAt,
        /** Always true on this shape */
        public readonly bool $corrected = true,
        /** Shifts only: who opened it — 'portal', 'operator' or 'supervisor' */
        public readonly ?string $source = null,
        /** Shifts only: 'manual', 'timeout' or 'removed'; null while open */
        public readonly ?string $endReason = null,
        /** Breaks only: the worker's stated reason */
        public readonly ?string $reason = null,
        /** Shifts only: the work note the record carries after the change */
        public readonly ?string $description = null,
        /** Shifts only: the task the record carries after the change */
        public readonly ?string $taskId = null,
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
            corrected: (bool) ($data['corrected'] ?? true),
            source: isset($data['source']) ? (string) $data['source'] : null,
            endReason: isset($data['endReason']) ? (string) $data['endReason'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            taskId: isset($data['taskId']) ? (string) $data['taskId'] : null,
        );
    }
}
