<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A `wt_` bearer token scoped to exactly one worker.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerSessionToken
{
    public function __construct(
        public readonly string $token,
        /**
         * ISO-8601 moment this token stops working, or null on a server predating the refresh
         * endpoint. Null rather than a defaulted timestamp: a made-up expiry would rotate either
         * far too early or never, and rotating only on a 401 puts a failed request in front of
         * a person at the start of every session.
         */
        public readonly ?string $expiresAt = null,
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
            expiresAt: isset($data['expiresAt']) ? (string) $data['expiresAt'] : null,
        );
    }
}
