<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A stored notification sequence.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class NotificationSequence
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $enabled,
        /** @var list<NotificationSequenceStep> */
        public readonly array $steps,
        /** @var list<string> */
        public readonly array $filterTags,
        /** ISO-8601 */
        public readonly string $createdAt,
        /** ISO-8601 */
        public readonly string $updatedAt,
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
            name: (string) ($data['name'] ?? ''),
            enabled: (bool) ($data['enabled'] ?? false),
            steps: Json::parseEach($data, 'steps', [NotificationSequenceStep::class, 'fromArray']),
            filterTags: \array_map('strval', $data['filterTags'] ?? []),
            createdAt: (string) ($data['createdAt'] ?? ''),
            updatedAt: (string) ($data['updatedAt'] ?? ''),
        );
    }
}
