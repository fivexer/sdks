<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A stored workflow graph.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $version,
        public readonly string $initialStepId,
        /** @var list<WorkflowStep> */
        public readonly array $steps,
        public readonly ?int $defaultTimeoutMs,
        /** @var array<string, mixed>|null */
        public readonly ?array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            version: (int) ($data['version'] ?? 0),
            initialStepId: (string) ($data['initialStepId'] ?? ''),
            steps: Json::parseEach($data, 'steps', [WorkflowStep::class, 'fromArray']),
            defaultTimeoutMs: isset($data['defaultTimeoutMs']) ? (int) $data['defaultTimeoutMs'] : null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
