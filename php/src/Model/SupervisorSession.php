<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A redeemed supervisor session. `expiresAt` is epoch-**milliseconds**, not the ISO-8601 string the worker plane sends — the two planes genuinely differ on the wire, and this mirrors the server rather than papering over it.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorSession
{
    public function __construct(
        /** An `sv_` supervisor session token. */
        public readonly string $token,
        /** Epoch-milliseconds. */
        public readonly int $expiresAt,
        public readonly SupervisorIdentity $supervisor,
        public readonly string $workspaceId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) ($data['token'] ?? ''),
            expiresAt: (int) ($data['expiresAt'] ?? 0),
            supervisor: SupervisorIdentity::fromArray($data['supervisor'] ?? ['id' => '']),
            workspaceId: (string) ($data['workspaceId'] ?? ''),
        );
    }
}
