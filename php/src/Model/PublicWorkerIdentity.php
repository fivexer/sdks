<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A worker's portal credential. Never carries the PIN — only whether one is set.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class PublicWorkerIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $workerId,
        public readonly string $label,
        /** 'active' | 'revoked' */
        public readonly string $status,
        public readonly ?string $email,
        public readonly bool $hasPin,
        public readonly ?string $activatedAt,
        /** ISO-8601 */
        public readonly string $createdAt,
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
            workerId: (string) ($data['workerId'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            hasPin: (bool) ($data['hasPin'] ?? false),
            activatedAt: isset($data['activatedAt']) ? (string) $data['activatedAt'] : null,
            createdAt: (string) ($data['createdAt'] ?? ''),
            revokedAt: isset($data['revokedAt']) ? (string) $data['revokedAt'] : null,
        );
    }
}
