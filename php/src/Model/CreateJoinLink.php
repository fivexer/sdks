<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for a QR join link: preset tags, skills and team for whoever scans it.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class CreateJoinLink
{
    public function __construct(
        private readonly string $label,
        private ?string $teamId = null,
        /** @var list<string>|null */
        private ?array $tags = null,
        /** @var list<WorkerSkillAssignment>|null */
        private ?array $skills = null,
        private ?bool $requiresApproval = null,
        private ?int $maxUses = null,
        private ?int $expiresInMs = null,
    ) {
    }

    public function teamId(string $teamId): self
    {
        $this->teamId = $teamId;
        return $this;
    }

    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function skills(array $skills): self
    {
        $this->skills = $skills;
        return $this;
    }

    public function requiresApproval(bool $requiresApproval): self
    {
        $this->requiresApproval = $requiresApproval;
        return $this;
    }

    public function maxUses(int $maxUses): self
    {
        $this->maxUses = $maxUses;
        return $this;
    }

    public function expiresInMs(int $expiresInMs): self
    {
        $this->expiresInMs = $expiresInMs;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
        ] + Json::compact([
            'teamId' => $this->teamId,
            'tags' => $this->tags,
            'skills' => Json::each($this->skills),
            'requiresApproval' => $this->requiresApproval,
            'maxUses' => $this->maxUses,
            'expiresInMs' => $this->expiresInMs,
        ]);
    }
}
