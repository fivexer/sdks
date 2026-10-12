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
        /** The requester's definition of done, fixed at creation; null when the task has none */
        public readonly ?ResultContract $resultContract = null,
        /**
         * Short-lived content that cannot be shown, and why: `expired` (the window passed) or
         * `withheld` (this caller may not read it). Null when shown as stored.
         */
        public readonly ?string $contentState = null,
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
            resultContract: ResultContract::fromParent($data),
            contentState: isset($data['contentState']) ? (string) $data['contentState'] : null,
        );
    }
}
