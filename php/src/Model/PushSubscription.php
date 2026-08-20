<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A registered browser Web Push subscription.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class PushSubscription
{
    public function __construct(
        public readonly string $endpoint,
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
            endpoint: (string) ($data['endpoint'] ?? ''),
            createdAt: (string) ($data['createdAt'] ?? ''),
        );
    }
}
