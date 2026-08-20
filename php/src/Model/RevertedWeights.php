<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Which workers had their learned routing weights restored.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class RevertedWeights
{
    public function __construct(
        /** @var list<string> */
        public readonly array $reverted,
        public readonly int $count,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reverted: \array_values(\array_map(strval(...), $data['reverted'] ?? [])),
            count: (int) ($data['count'] ?? 0),
        );
    }
}
