<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The result of pausing or resuming a worker.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerAvailability
{
    public function __construct(
        public readonly string $id,
        public readonly bool $available,
        /** @var list<string>|null */
        public readonly ?array $releasedTaskIds,
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
            available: (bool) ($data['available'] ?? false),
            releasedTaskIds: isset($data['releasedTaskIds']) ? \array_map('strval', $data['releasedTaskIds']) : null,
        );
    }
}
