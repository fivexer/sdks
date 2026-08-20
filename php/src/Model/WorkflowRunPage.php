<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A cursor-paginated page of runs.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowRunPage
{
    public function __construct(
        /** @var list<WorkflowRun> */
        public readonly array $runs,
        public readonly ?string $nextCursor,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            runs: Json::parseEach($data, 'runs', [WorkflowRun::class, 'fromArray']),
            nextCursor: isset($data['nextCursor']) ? (string) $data['nextCursor'] : null,
        );
    }
}
