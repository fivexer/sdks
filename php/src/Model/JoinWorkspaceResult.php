<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * What a worker gets back after self-registering through a QR join link. `workerId` is server-generated — show it to them, it is their PIN-login username.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class JoinWorkspaceResult
{
    public function __construct(
        /** A `wt_` worker session token — they are signed in already. */
        public readonly string $token,
        public readonly string $workspaceId,
        public readonly string $workerId,
        public readonly string $label,
        public readonly ?string $expiresAt,
        public readonly bool $pendingApproval,
        public readonly string $portalUrl,
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
            workspaceId: (string) ($data['workspaceId'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            expiresAt: isset($data['expiresAt']) ? (string) $data['expiresAt'] : null,
            pendingApproval: (bool) ($data['pendingApproval'] ?? false),
            portalUrl: (string) ($data['portalUrl'] ?? ''),
        );
    }
}
