<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The supervisor a session belongs to.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $email,
        /** Null for a workspace-wide supervisor. */
        public readonly ?string $teamId,
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
            email: isset($data['email']) ? (string) $data['email'] : null,
            teamId: isset($data['teamId']) ? (string) $data['teamId'] : null,
        );
    }
}
