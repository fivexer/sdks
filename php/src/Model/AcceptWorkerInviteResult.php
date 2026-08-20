<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * What a worker gets back after consuming an emailed invite. Unlike {@see JoinWorkspaceResult} the worker id already existed — an operator created the identity when they sent the invite.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class AcceptWorkerInviteResult
{
    public function __construct(
        public readonly string $token,
        public readonly string $workspaceId,
        public readonly string $workerId,
        public readonly string $label,
        public readonly ?string $expiresAt,
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
            portalUrl: (string) ($data['portalUrl'] ?? ''),
        );
    }
}
