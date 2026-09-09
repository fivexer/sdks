<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A worker's own language, after setting it.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerLocaleState
{
    public function __construct(
        public readonly string $workerId,
        public readonly ?string $locale,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
        );
    }
}
