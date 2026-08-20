<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Why one entry of a bulk create failed.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class BulkTaskError
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? ''),
            message: (string) ($data['message'] ?? ''),
        );
    }
}
