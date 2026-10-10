<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Leave settled when a worker leaves: minutes paid out, and minutes recovered
 * (taken beyond what was earned).
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class LeavePayout
{
    public function __construct(
        public readonly int $paidOutMinutes,
        public readonly int $recoveredMinutes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paidOutMinutes: (int) ($data['paidOutMinutes'] ?? 0),
            recoveredMinutes: (int) ($data['recoveredMinutes'] ?? 0),
        );
    }
}
