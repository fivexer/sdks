<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Counts of the rich data hanging off a task; populated on single-task reads only.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskDataSummary
{
    public function __construct(
        public readonly bool $hasContext,
        public readonly int $referenceCount,
        public readonly int $attachmentCount,
        public readonly int $commentCount,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hasContext: (bool) ($data['hasContext'] ?? false),
            referenceCount: (int) ($data['referenceCount'] ?? 0),
            attachmentCount: (int) ($data['attachmentCount'] ?? 0),
            commentCount: (int) ($data['commentCount'] ?? 0),
        );
    }
}
