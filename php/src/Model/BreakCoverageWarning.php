<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A shift a break would leave short. Advisory: the break is still allowed.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class BreakCoverageWarning
{
    public function __construct(
        public readonly string $shiftInstanceId,
        public readonly string $shiftName,
        public readonly int $remaining,
        public readonly int $required,
        /** 'headcount' | 'qualification' */
        public readonly string $kind,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            shiftInstanceId: (string) ($data['shiftInstanceId'] ?? ''),
            shiftName: (string) ($data['shiftName'] ?? ''),
            remaining: (int) ($data['remaining'] ?? 0),
            required: (int) ($data['required'] ?? 0),
            kind: (string) ($data['kind'] ?? 'headcount'),
        );
    }
}
