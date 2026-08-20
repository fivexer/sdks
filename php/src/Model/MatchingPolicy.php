<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The live balancer policy applied to bulk matching passes.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class MatchingPolicy
{
    public function __construct(
        /** first-come | best-match | balanced | spread-work */
        public readonly string $fairness,
        public readonly ?int $maxTasksPerWindow,
        public readonly ?int $windowMs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fairness: (string) ($data['fairness'] ?? 'first-come'),
            maxTasksPerWindow: isset($data['maxTasksPerWindow']) ? (int) $data['maxTasksPerWindow'] : null,
            windowMs: isset($data['windowMs']) ? (int) $data['windowMs'] : null,
        );
    }
}
