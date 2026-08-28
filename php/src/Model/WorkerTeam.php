<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** One team membership as it appears on a worker's detail record. */
final class WorkerTeam
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $key,
        public readonly string $name,
        public readonly ?string $color = null,
        /** 'member' | 'lead' */
        public readonly string $role = 'member',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['teamId'] ?? ''),
            (string) ($data['key'] ?? ''),
            (string) ($data['name'] ?? ''),
            isset($data['color']) ? (string) $data['color'] : null,
            (string) ($data['role'] ?? 'member'),
        );
    }
}
