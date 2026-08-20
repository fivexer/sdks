<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The rich data stored against a task.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskContext
{
    public function __construct(
        public readonly string $taskId,
        public readonly ?string $title,
        public readonly ?string $description,
        /** @var array<string, mixed>|null */
        public readonly ?array $context,
        /** @var list<TaskReference> */
        public readonly array $references,
        public readonly ?int $createdAt,
        public readonly ?int $updatedAt,
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
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            context: $data['context'] ?? null,
            references: Json::parseEach($data, 'references', [TaskReference::class, 'fromArray']),
            createdAt: isset($data['createdAt']) ? (int) $data['createdAt'] : null,
            updatedAt: isset($data['updatedAt']) ? (int) $data['updatedAt'] : null,
        );
    }
}
