<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A dry run: what would happen to this task. Creates and reserves nothing.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskCheckReport
{
    public function __construct(
        /** @var list<TaskCheckIssue> */
        public readonly array $issues,
        public readonly int $eligibleWorkerCount,
        /** @var list<string> */
        public readonly array $uncoveredTags,
        /** Epoch-ms */
        public readonly int $evaluatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            issues: Json::parseEach($data, 'issues', [TaskCheckIssue::class, 'fromArray']),
            eligibleWorkerCount: (int) ($data['eligibleWorkerCount'] ?? 0),
            uncoveredTags: \array_values(\array_map(strval(...), $data['uncoveredTags'] ?? [])),
            evaluatedAt: (int) ($data['evaluatedAt'] ?? 0),
        );
    }
}
