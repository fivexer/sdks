<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The result of starting a break.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerBreakStarted
{
    public function __construct(
        public readonly string $workerId,
        public readonly bool $onBreak,
        public readonly string $since,
        /**
         * Shifts this break leaves short. Advisory — the break was taken.
         *
         * @var list<BreakCoverageWarning>
         */
        public readonly array $coverageWarnings = [],
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
            onBreak: (bool) ($data['onBreak'] ?? false),
            since: (string) ($data['since'] ?? ''),
            coverageWarnings: Json::parseEach($data, 'coverageWarnings', [BreakCoverageWarning::class, 'fromArray']),
        );
    }
}
