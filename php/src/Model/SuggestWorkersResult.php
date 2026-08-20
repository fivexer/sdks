<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The result of a dry-run scoring pass.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SuggestWorkersResult
{
    public function __construct(
        /** @var list<string> */
        public readonly array $tags,
        public readonly float $priority,
        /** @var list<SuggestedWorker> */
        public readonly array $workers,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tags: \array_map('strval', $data['tags'] ?? []),
            priority: (float) ($data['priority'] ?? 0),
            workers: Json::parseEach($data, 'workers', [SuggestedWorker::class, 'fromArray']),
        );
    }
}
