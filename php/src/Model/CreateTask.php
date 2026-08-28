<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for POST /v1/tasks. `tags` is required; the rest are optional.
 *
 * Carries the rich-data fields too (title/description/context/references), so a task can be
 * created with its full context in one call instead of a create plus a separate context write.
 *
 * Fluent builder: call setters, then pass to $client->tasks()->create($input).
 */
final class CreateTask
{
    /** @param list<string> $tags */
    public function __construct(
        private readonly array $tags,
        private ?string $id = null,
        private ?float $priority = null,
        /** @var array<string, float>|null */
        private ?array $skillThresholds = null,
        /** @var list<string>|null */
        private ?array $vetoedWorkers = null,
        /** @var array<string, mixed>|null */
        private ?array $meta = null,
        private ?string $title = null,
        private ?string $description = null,
        /** @var array<string, mixed>|null */
        private ?array $context = null,
        /** @var list<TaskReferenceInput>|null */
        private ?array $references = null,
        private ?float $latitude = null,
        private ?float $longitude = null,
        private ?float $maxDistanceKm = null,
        /** Exclude workers without coordinates */
        private ?bool $requireGeo = null,
        /** @var list<string>|null */
        private ?array $allowedCidrs = null,
        /** @var list<RequiredSkill>|null Hard skill gate in catalog terms */
        private ?array $requiredSkills = null,
        /** Response clock. Absent inherits the workspace default; see escalationNone() */
        private ?EscalationPolicy $escalation = null,
        /** Completion clock, shelf life and rejection budget. Same inheritance rule */
        private ?SlaPolicy $sla = null,
        /** When this task may be offered. No default to inherit — timestamps are absolute */
        private ?SchedulePolicy $schedule = null,
        /** Makes this a standing template instead of a one-off. Exclusive with $schedule */
        private ?RecurrencePolicy $recurrence = null,
        /** Hard team gate: only members are eligible. Exclusive with $preferTeamId */
        private ?string $teamId = null,
        /** Soft team preference: members rank first, everyone else stays eligible */
        private ?string $preferTeamId = null,
    ) {
        if ($tags === []) {
            throw new \InvalidArgumentException('tags must not be empty');
        }
    }

    /**
     * Records a *request* for an explicit null, which toArray() writes onto the payload. Not a
     * wire field of its own — an absent policy and a null policy are different instructions, and
     * this is the only way to tell them apart once Json::compact() has run.
     */
    private bool $escalationNull = false;

    private bool $slaNull = false;

    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function priority(float $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /** @param array<string, float> $skillThresholds */
    public function skillThresholds(array $skillThresholds): self
    {
        $this->skillThresholds = $skillThresholds;
        return $this;
    }

    /** @param list<string> $vetoedWorkers */
    public function vetoedWorkers(array $vetoedWorkers): self
    {
        $this->vetoedWorkers = $vetoedWorkers;
        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function context(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /** @param list<TaskReferenceInput> $references */
    public function references(array $references): self
    {
        $this->references = $references;
        return $this;
    }

    public function latitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function longitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function maxDistanceKm(float $maxDistanceKm): self
    {
        $this->maxDistanceKm = $maxDistanceKm;
        return $this;
    }

    public function requireGeo(bool $requireGeo): self
    {
        $this->requireGeo = $requireGeo;
        return $this;
    }

    /** @param list<string> $allowedCidrs */
    public function allowedCidrs(array $allowedCidrs): self
    {
        $this->allowedCidrs = $allowedCidrs;
        return $this;
    }

    /** Minimum catalog skill levels a worker must hold to be eligible. */
    /** @param list<RequiredSkill> $requiredSkills */
    public function requiredSkills(array $requiredSkills): self
    {
        $this->requiredSkills = $requiredSkills;
        return $this;
    }

    /**
     * Response clock for this task. Omitting it inherits the workspace default; to opt out of
     * that default entirely, call {@see self::escalationNone()} — an absent field and an
     * explicit null are different instructions to the server.
     */
    public function escalation(EscalationPolicy $escalation): self
    {
        $this->escalation = $escalation;
        $this->escalationNull = false;
        return $this;
    }

    /** Send `"escalation": null` — opt this task out of the workspace default. */
    public function escalationNone(): self
    {
        $this->escalation = null;
        $this->escalationNull = true;
        return $this;
    }

    /** Completion clock, shelf life and rejection budget. Same inheritance rule as escalation. */
    public function sla(SlaPolicy $sla): self
    {
        $this->sla = $sla;
        $this->slaNull = false;
        return $this;
    }

    /** Send `"sla": null` — opt this task out of the workspace default. */
    public function slaNone(): self
    {
        $this->sla = null;
        $this->slaNull = true;
        return $this;
    }

    /** When this task may be offered. Timestamps are absolute epoch-milliseconds. */
    public function schedule(SchedulePolicy $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    /** Make this a standing template instead of a one-off. Exclusive with a schedule. */
    public function recurrence(RecurrencePolicy $recurrence): self
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    /** Hard team gate: only members are eligible. Exclusive with {@see self::preferTeamId()}. */
    public function teamId(string $teamId): self
    {
        $this->teamId = $teamId;
        return $this;
    }

    /** Soft team preference: members rank first, everyone else stays eligible. */
    public function preferTeamId(string $preferTeamId): self
    {
        $this->preferTeamId = $preferTeamId;
        return $this;
    }

    /**
     * Serializes only the set fields (omits nulls) so the request body matches the contract.
     *
     * The two nullable policies are the exception to that rule: an *absent* escalation or SLA
     * inherits the workspace default, while an explicit null opts out of it. Json::compact()
     * cannot express the second, so the opt-out is written on afterwards.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'tags' => $this->tags,
        ] + Json::compact([
            'id' => $this->id,
            'priority' => $this->priority,
            'skillThresholds' => $this->skillThresholds,
            'vetoedWorkers' => $this->vetoedWorkers,
            'meta' => $this->meta,
            'title' => $this->title,
            'description' => $this->description,
            'context' => $this->context,
            'references' => Json::each($this->references),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'maxDistanceKm' => $this->maxDistanceKm,
            'requireGeo' => $this->requireGeo,
            'allowedCidrs' => $this->allowedCidrs,
            'requiredSkills' => Json::each($this->requiredSkills),
            'escalation' => $this->escalation?->toArray(),
            'sla' => $this->sla?->toArray(),
            'schedule' => $this->schedule?->toArray(),
            'recurrence' => $this->recurrence?->toArray(),
            'teamId' => $this->teamId,
            'preferTeamId' => $this->preferTeamId,
        ]);

        if ($this->escalationNull) {
            $payload['escalation'] = null;
        }
        if ($this->slaNull) {
            $payload['sla'] = null;
        }
        return $payload;
    }
}
