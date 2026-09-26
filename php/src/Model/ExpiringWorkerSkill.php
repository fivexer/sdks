<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One qualification that has lapsed, or is about to.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class ExpiringWorkerSkill
{
    public function __construct(
        public readonly string $workerId,
        /** The person's portal label where they have one, otherwise their worker id */
        public readonly string $label,
        public readonly string $skillId,
        public readonly string $key,
        public readonly string $name,
        /** Inclusive last day it may be relied on — always set on this shape */
        public readonly string $validUntil,
        /** Already lapsed, judged against the response's asOf day */
        public readonly bool $expired,
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
            skillId: (string) ($data['skillId'] ?? ''),
            key: (string) ($data['key'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            validUntil: (string) ($data['validUntil'] ?? ''),
            expired: (bool) ($data['expired'] ?? false),
        );
    }
}
