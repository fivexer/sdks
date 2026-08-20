<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * What applying the learned weights would change.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class LearnedWeightsPreview
{
    public function __construct(
        /** @var list<PreviewedWorkerWeights> */
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
            workers: Json::parseEach($data, 'workers', [PreviewedWorkerWeights::class, 'fromArray']),
        );
    }
}
