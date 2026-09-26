<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * What removing a worker would hand back and free. Read-only — show it before
 * `workers()->remove()`.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerOffboardingSummary
{
    public function __construct(
        public readonly string $workerId,
        /** Their last working day; shifts after it are freed */
        public readonly string $lastDay,
        /** Work that goes back to the queue */
        public readonly WorkerOffboardingTasks $tasks,
        /** @var list<WorkerOffboardingRoster> */
        public readonly array $rosters,
        public readonly int $coverRequests,
        public readonly int $coverOffers,
        public readonly int $swaps,
        public readonly int $pendingTimeOff,
        public readonly int $leavePolicies,
        public readonly int $teams,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            lastDay: (string) ($data['lastDay'] ?? ''),
            tasks: WorkerOffboardingTasks::fromArray($data['tasks'] ?? []),
            rosters: Json::parseEach($data, 'rosters', [WorkerOffboardingRoster::class, 'fromArray']),
            coverRequests: (int) ($data['coverRequests'] ?? 0),
            coverOffers: (int) ($data['coverOffers'] ?? 0),
            swaps: (int) ($data['swaps'] ?? 0),
            pendingTimeOff: (int) ($data['pendingTimeOff'] ?? 0),
            leavePolicies: (int) ($data['leavePolicies'] ?? 0),
            teams: (int) ($data['teams'] ?? 0),
        );
    }
}
