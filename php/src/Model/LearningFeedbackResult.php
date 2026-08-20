<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Bulk feedback is partial-success: each item reports its own outcome.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class LearningFeedbackResult
{
    public function __construct(
        public readonly string $taskId,
        public readonly bool $ok,
        public readonly ?string $error,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: (string) ($data['taskId'] ?? ''),
            ok: (bool) ($data['ok'] ?? false),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
