<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Whether this deployment can send Web Push, and the VAPID key to subscribe with. `enabled: false` is a deployment fact, not an error — read it before prompting for notification permission, because a browser only gives you one prompt.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class PushConfig
{
    public function __construct(
        public readonly bool $enabled,
        /** Null exactly when `enabled` is false. */
        public readonly ?string $publicKey,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            publicKey: isset($data['publicKey']) ? (string) $data['publicKey'] : null,
        );
    }
}
