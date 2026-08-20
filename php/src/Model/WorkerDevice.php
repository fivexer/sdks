<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A registered Expo push token for the native app.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerDevice
{
    public function __construct(
        public readonly string $token,
        /** 'ios' | 'android' | 'unknown' */
        public readonly string $platform,
        public readonly ?string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) ($data['token'] ?? ''),
            platform: (string) ($data['platform'] ?? ''),
            createdAt: isset($data['createdAt']) ? (string) $data['createdAt'] : null,
        );
    }
}
