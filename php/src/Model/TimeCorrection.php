<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One change made to a recorded shift or break.
 *
 * Never a silent overwrite: what the row said before is kept beside what it says now, the reason
 * is mandatory, and the author is stamped on it. This is the row a dispute reads.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TimeCorrection
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    public function __construct(
        public readonly string $id,
        /** 'shift' or 'break' */
        public readonly string $entryType,
        public readonly string $entryId,
        public readonly string $workerId,
        /** What the row held before; null when this change created it */
        public readonly ?array $before,
        public readonly array $after,
        public readonly string $note,
        /** Denormalized at write time, so the trail survives the author leaving */
        public readonly ?string $by,
        public readonly string $at,
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
            entryType: (string) ($data['entryType'] ?? ''),
            entryId: (string) ($data['entryId'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            before: isset($data['before']) && \is_array($data['before']) ? $data['before'] : null,
            after: \is_array($data['after'] ?? null) ? $data['after'] : [],
            note: (string) ($data['note'] ?? ''),
            by: isset($data['by']) ? (string) $data['by'] : null,
            at: (string) ($data['at'] ?? ''),
        );
    }
}
