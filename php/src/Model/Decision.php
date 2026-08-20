<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A decision trace from GET /v1/decisions - who was matched, and why.
 * The full per-candidate score breakdown lives in DecisionCandidate::detail.
 */
final class Decision
{
    /**
     * @param list<DecisionCandidate> $candidates
     */
    public function __construct(
        public readonly string $id,
        public readonly string $taskId,
        public readonly string $workerId,
        public readonly int $matchedAt,
        public readonly string $mode,
        public readonly array $candidates,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $candidates = \array_map(
            static fn(array $c): DecisionCandidate => DecisionCandidate::fromArray($c),
            $data['candidates'] ?? [],
        );
        return new self(
            id: (string) $data['id'],
            taskId: (string) $data['taskId'],
            workerId: (string) $data['workerId'],
            matchedAt: (int) $data['matchedAt'],
            mode: (string) ($data['mode'] ?? ''),
            candidates: $candidates,
        );
    }
}
