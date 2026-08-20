<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A QR join link. `maxUses` is null for unlimited; 0 would mean exhausted.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class JoinLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        /** 'active' | 'revoked' | 'expired' | 'exhausted' */
        public readonly string $status,
        public readonly ?string $teamId,
        /** @var list<string> */
        public readonly array $tags,
        public readonly bool $requiresApproval,
        /** Null means unlimited — 0 would mean exhausted. */
        public readonly ?int $maxUses,
        public readonly int $useCount,
        /** ISO-8601 */
        public readonly string $createdAt,
        /** ISO-8601 */
        public readonly string $expiresAt,
        public readonly ?string $revokedAt,
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
            label: (string) ($data['label'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            teamId: isset($data['teamId']) ? (string) $data['teamId'] : null,
            tags: \array_values(\array_map(strval(...), $data['tags'] ?? [])),
            requiresApproval: (bool) ($data['requiresApproval'] ?? false),
            maxUses: isset($data['maxUses']) ? (int) $data['maxUses'] : null,
            useCount: (int) ($data['useCount'] ?? 0),
            createdAt: (string) ($data['createdAt'] ?? ''),
            expiresAt: (string) ($data['expiresAt'] ?? ''),
            revokedAt: isset($data['revokedAt']) ? (string) $data['revokedAt'] : null,
        );
    }
}
