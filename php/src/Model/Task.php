<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A task on the /v1 surface. Timestamps are epoch-milliseconds; meta is an arbitrary object.
 *
 * Response models are populated from decoded JSON arrays; instances are read-only.
 *
 * @phpstan-import-type TaskData from Types
 */
final class Task
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed>|null $meta
     * @param array<string, mixed>|null $result Present only on archived reads
     */
    public function __construct(
        public readonly string $id,
        public readonly array $tags,
        public readonly ?float $priority,
        public readonly string $status,
        public readonly ?string $workerId,
        public readonly ?int $createdAt,
        public readonly ?array $meta,
        public readonly ?array $result = null,
        public readonly bool $archived = false,
        /** Truncated copy; the full value lives on tasks()->context() */
        public readonly ?string $title = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $maxDistanceKm = null,
        public readonly ?bool $requireGeo = null,
        /** @var list<string>|null */
        public readonly ?array $allowedCidrs = null,
        /** Set on workflow-step tasks */
        public readonly ?string $workflowRunId = null,
        public readonly ?string $workflowStepId = null,
        /** Populated on single-task reads only, never in lists */
        public readonly ?TaskDataSummary $data = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            tags: \array_map('strval', $data['tags'] ?? []),
            priority: isset($data['priority']) ? (float) $data['priority'] : null,
            status: (string) ($data['status'] ?? 'queued'),
            workerId: isset($data['workerId']) ? (string) $data['workerId'] : null,
            createdAt: isset($data['createdAt']) ? (int) $data['createdAt'] : null,
            meta: $data['meta'] ?? null,
            result: $data['result'] ?? null,
            archived: (bool) ($data['archived'] ?? false),
            title: isset($data['title']) ? (string) $data['title'] : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            maxDistanceKm: isset($data['maxDistanceKm']) ? (float) $data['maxDistanceKm'] : null,
            requireGeo: isset($data['requireGeo']) ? (bool) $data['requireGeo'] : null,
            allowedCidrs: isset($data['allowedCidrs']) ? \array_map('strval', $data['allowedCidrs']) : null,
            workflowRunId: isset($data['workflowRunId']) ? (string) $data['workflowRunId'] : null,
            workflowStepId: isset($data['workflowStepId']) ? (string) $data['workflowStepId'] : null,
            data: isset($data['data']) ? TaskDataSummary::fromArray($data['data']) : null,
        );
    }
}
