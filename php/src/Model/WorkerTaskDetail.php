<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Rich detail for a task assigned to the authenticated worker.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerTaskDetail
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        /** @var list<string> */
        public readonly array $tags,
        public readonly ?float $priority,
        public readonly ?string $title,
        public readonly ?string $description,
        /** @var array<string, mixed>|null */
        public readonly ?array $context,
        /** @var list<TaskReference>|null */
        public readonly ?array $references,
        public readonly ?int $createdAt,
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
            status: (string) ($data['status'] ?? ''),
            tags: \array_map('strval', $data['tags'] ?? []),
            priority: isset($data['priority']) ? (float) $data['priority'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            context: $data['context'] ?? null,
            references: Json::parseEachOrNull($data, 'references', [TaskReference::class, 'fromArray']),
            createdAt: isset($data['createdAt']) ? (int) $data['createdAt'] : null,
        );
    }
}
