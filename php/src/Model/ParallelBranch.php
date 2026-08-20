<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One branch of a parallel step.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class ParallelBranch
{
    public function __construct(
        public readonly string $stepId,
        public readonly string $assignmentId,
        public readonly string $status,
        /** @var array<string, mixed>|null */
        public readonly ?array $result,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            stepId: (string) ($data['stepId'] ?? ''),
            assignmentId: (string) ($data['assignmentId'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            result: $data['result'] ?? null,
        );
    }
}
