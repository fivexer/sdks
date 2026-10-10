<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A stretch of a recorded shift spent in one role, with the role's name.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class RoleStretch
{
    public function __construct(
        /** A role id on the rate card; null is time in no particular role. */
        public readonly ?string $positionId,
        /** ISO-8601 */
        public readonly string $startedAt,
        /** Null only for the stretch still being worked on a running shift. */
        public readonly ?string $endedAt,
        /** The role's name; null for time in no particular role. */
        public readonly ?string $label,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            positionId: isset($data['positionId']) ? (string) $data['positionId'] : null,
            startedAt: (string) ($data['startedAt'] ?? ''),
            endedAt: isset($data['endedAt']) ? (string) $data['endedAt'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }
}
