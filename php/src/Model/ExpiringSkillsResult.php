<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Qualifications that have lapsed or are about to, already-expired rows first.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class ExpiringSkillsResult
{
    public function __construct(
        /** The day `expired` was judged against */
        public readonly string $asOf,
        /** @var list<ExpiringWorkerSkill> */
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
            asOf: (string) ($data['asOf'] ?? ''),
            skills: Json::parseEach($data, 'skills', [ExpiringWorkerSkill::class, 'fromArray']),
        );
    }
}
