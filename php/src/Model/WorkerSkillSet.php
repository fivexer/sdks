<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A worker's skills after replacing the whole set.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerSkillSet
{
    public function __construct(
        public readonly string $workerId,
        /** @var list<WorkerSkill> */
        public readonly array $skills,
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
            skills: Json::parseEach($data, 'skills', [WorkerSkill::class, 'fromArray']),
        );
    }
}
