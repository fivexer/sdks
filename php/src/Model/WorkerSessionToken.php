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
        );
    }
}
