<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Full detail of one worker, including current load.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerDetail
{
    public function __construct(
        public readonly string $id,
        /** @var list<string> */
        public readonly array $tags,
        /** @var array<string, float>|null */
        public readonly ?array $routingWeights,
        /** @var list<WorkerSkill>|null */
        public readonly ?array $skills,
        /** 0 means receive nothing; null means no per-worker cap */
        public readonly ?int $maxBacklogSize,
        public readonly bool $available,
        public readonly int $queueDepth,
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
            tags: \array_map('strval', $data['tags'] ?? []),
            routingWeights: $data['routingWeights'] ?? null,
            skills: Json::parseEachOrNull($data, 'skills', [WorkerSkill::class, 'fromArray']),
            maxBacklogSize: isset($data['maxBacklogSize']) ? (int) $data['maxBacklogSize'] : null,
            available: (bool) ($data['available'] ?? false),
            queueDepth: (int) ($data['queueDepth'] ?? 0),
        );
    }
}
