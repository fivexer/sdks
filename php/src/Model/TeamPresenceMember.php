<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker presence.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamPresenceMember
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $label,
        /** working | on-break | paused */
        public readonly string $status,
        public readonly ?string $breakStartedAt,
        public readonly ?string $breakReason,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            breakStartedAt: isset($data['breakStartedAt']) ? (string) $data['breakStartedAt'] : null,
            breakReason: isset($data['breakReason']) ? (string) $data['breakReason'] : null,
        );
    }
}
