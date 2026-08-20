<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A delivery target. The signing secret is write-only and never returned.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class NotificationChannel
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $target,
        /** @var list<string> */
        public readonly array $events,
        public readonly bool $disabled,
        /** ISO-8601 */
        public readonly string $createdAt,
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
            type: (string) ($data['type'] ?? ''),
            target: (string) ($data['target'] ?? ''),
            events: \array_map('strval', $data['events'] ?? []),
            disabled: (bool) ($data['disabled'] ?? false),
            createdAt: (string) ($data['createdAt'] ?? ''),
        );
    }
}
