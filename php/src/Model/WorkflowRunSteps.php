<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The per-step view of a run - what a canvas UI paints.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowRunSteps
{
    public function __construct(
        public readonly string $runId,
        public readonly string $status,
        /** @var list<WorkflowRunStep> */
        public readonly array $steps,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            runId: (string) ($data['runId'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            steps: Json::parseEach($data, 'steps', [WorkflowRunStep::class, 'fromArray']),
        );
    }
}
