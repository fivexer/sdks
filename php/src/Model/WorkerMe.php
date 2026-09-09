<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Who am I, and am I on shift? Works on data-plane-only deployments, where the break and team endpoints 501 — `onBreak` is simply always false there.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMe
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $label,
        public readonly bool $available,
        public readonly bool $pendingApproval,
        public readonly bool $onBreak,
        public readonly ?string $breakStartedAt,
        /** @var list<WorkerSkill> */
        public readonly array $skills,
        public readonly bool $skillSetupPending,
        public readonly ?string $locale = null,
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
            label: (string) ($data['label'] ?? ''),
            available: (bool) ($data['available'] ?? false),
            pendingApproval: (bool) ($data['pendingApproval'] ?? false),
            onBreak: (bool) ($data['onBreak'] ?? false),
            breakStartedAt: isset($data['breakStartedAt']) ? (string) $data['breakStartedAt'] : null,
            skills: Json::parseEach($data, 'skills', [WorkerSkill::class, 'fromArray']),
            skillSetupPending: (bool) ($data['skillSetupPending'] ?? false),
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
        );
    }
}
