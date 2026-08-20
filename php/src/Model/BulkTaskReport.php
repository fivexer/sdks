<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Outcome of a bulk create. A 200 does not mean everything was created: read `failed` and the per-entry results, whose `index` maps back to the input list.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class BulkTaskReport
{
    public function __construct(
        public readonly int $created,
        public readonly int $failed,
        /** @var list<BulkTaskResult> */
        public readonly array $results,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            created: (int) ($data['created'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            results: Json::parseEach($data, 'results', [BulkTaskResult::class, 'fromArray']),
        );
    }
}
