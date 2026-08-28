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
        /**
         * Which entries of $routingWeights the learning layer owns, and when it last wrote them
         * — the difference between "an operator vetoed this tag" and "the model did", which is
         * the difference between a decision to keep and one to revert.
         *
         * @var array<string, float>|null
         */
        public readonly ?array $learnedRoutingWeights = null,
        /**
         * What $routingWeights held before the last sync; restored by revertWeights().
         *
         * @var array<string, float>|null
         */
        public readonly ?array $routingWeightsSnapshot = null,
        public readonly ?int $learnedRoutingWeightsSyncedAt = null,
        /** @var list<WorkerTeam>|null */
        public readonly ?array $teams = null,
        /**
         * Why they are unavailable when it is not simply "off shift": an invite nobody accepted,
         * or a QR join waiting on operator approval. An operator UI that renders either as
         * "paused" tells the wrong story — nobody has to resume an invite, they have to chase it.
         */
        public readonly bool $invitePending = false,
        public readonly bool $pendingApproval = false,
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
            learnedRoutingWeights: $data['learnedRoutingWeights'] ?? null,
            routingWeightsSnapshot: $data['routingWeightsSnapshot'] ?? null,
            learnedRoutingWeightsSyncedAt: isset($data['learnedRoutingWeightsSyncedAt'])
                ? (int) $data['learnedRoutingWeightsSyncedAt']
                : null,
            teams: Json::parseEachOrNull($data, 'teams', [WorkerTeam::class, 'fromArray']),
            invitePending: (bool) ($data['invitePending'] ?? false),
            pendingApproval: (bool) ($data['pendingApproval'] ?? false),
        );
    }
}
