<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * `GET /v1/tasks/{id}/outcome` — where a task stands and, once finished, its result.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskOutcome
{
    public function __construct(
        public readonly string $id,
        /** queued, pending, accepted, … while live; completed, failed, cancelled or expired once done */
        public readonly string $status,
        public readonly bool $done,
        /** Who holds or held it; null while queued */
        public readonly ?string $workerId,
        /** @var array<string, mixed>|null */
        public readonly ?array $result,
        /** Epoch ms; null while live */
        public readonly ?int $terminalAt,
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
            status: (string) ($data['status'] ?? ''),
            done: (bool) ($data['done'] ?? false),
            workerId: isset($data['workerId']) ? (string) $data['workerId'] : null,
            result: $data['result'] ?? null,
            terminalAt: isset($data['terminalAt']) ? (int) $data['terminalAt'] : null,
        );
    }
}
