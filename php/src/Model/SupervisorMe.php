<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The signed-in supervisor and their scope. A null `teamKey` is a real answer: it means the whole workspace, not a missing value.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorMe
{
    public function __construct(
        public readonly string $supervisorId,
        public readonly string $label,
        public readonly ?string $email,
        public readonly string $workspaceId,
        /** Null means the whole workspace. */
        public readonly ?string $teamKey,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            supervisorId: (string) ($data['supervisorId'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            workspaceId: (string) ($data['workspaceId'] ?? ''),
            teamKey: isset($data['teamKey']) ? (string) $data['teamKey'] : null,
        );
    }
}
