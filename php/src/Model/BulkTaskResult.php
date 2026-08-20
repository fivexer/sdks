<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One entry of a bulk create. The wire sends a union — either an id or an error — so exactly one of `id`/`error` is set; {@see self::ok()} is the discriminator.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class BulkTaskResult
{
    public function __construct(
        /** Position in the caller's input list. */
        public readonly int $index,
        public readonly ?string $id,
        public readonly ?string $status,
        public readonly ?BulkTaskError $error,
    ) {
    }

    /** True when this entry was created; false when it carries an error instead. */
    public function ok(): bool
    {
        return $this->error === null;
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: (int) ($data['index'] ?? 0),
            id: isset($data['id']) ? (string) $data['id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            error: isset($data['error']) && \is_array($data['error']) ? BulkTaskError::fromArray($data['error']) : null,
        );
    }
}
