<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One problem found by the dry-run check.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskCheckIssue
{
    public function __construct(
        /** 'error' | 'warning' | 'info' */
        public readonly string $severity,
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $tag,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            severity: (string) ($data['severity'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            tag: isset($data['tag']) ? (string) $data['tag'] : null,
        );
    }
}
