<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for emailing a worker a set-your-PIN link. Creates the worker too when it does not exist yet, which the result reports as `workerCreated`.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class InviteWorkerIdentity
{
    public function __construct(
        private readonly string $email,
        private ?string $workerId = null,
        private ?string $label = null,
        /** @var list<string>|null */
        private ?array $tags = null,
        /** @var list<WorkerSkillAssignment>|null */
        private ?array $skills = null,
        /** @var list<string>|null */
        private ?array $teamIds = null,
    ) {
    }

    public function workerId(string $workerId): self
    {
        $this->workerId = $workerId;
        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;
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

    public function teamIds(array $teamIds): self
    {
        $this->teamIds = $teamIds;
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
            'email' => $this->email,
        ] + Json::compact([
            'workerId' => $this->workerId,
            'label' => $this->label,
            'tags' => $this->tags,
            'skills' => Json::each($this->skills),
            'teamIds' => $this->teamIds,
        ]);
    }
}
