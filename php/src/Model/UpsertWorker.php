<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for POST /v1/workers (create or update). Every field is optional; an absent id means
 * the server assigns one.
 */
final class UpsertWorker
{
    /** @param list<string>|null $tags */
    public function __construct(
        private ?string $id = null,
        private ?array $tags = null,
        /** @var array<string, float>|null */
        private ?array $routingWeights = null,
        /** @var list<WorkerSkillAssignment>|null */
        private ?array $skills = null,
        private ?string $ip = null,
        private ?float $latitude = null,
        private ?float $longitude = null,
        private ?float $maxTravelDistanceKm = null,
        /** Per-worker backlog cap overriding the workspace default; 0 = receive nothing */
        private ?int $maxBacklogSize = null,
    ) {
    }

    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /** @param list<string> $tags */
    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    /** @param array<string, float> $routingWeights */
    public function routingWeights(array $routingWeights): self
    {
        $this->routingWeights = $routingWeights;
        return $this;
    }

    /** @param list<WorkerSkillAssignment> $skills */
    public function skills(array $skills): self
    {
        $this->skills = $skills;
        return $this;
    }

    public function ip(string $ip): self
    {
        $this->ip = $ip;
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

    public function maxTravelDistanceKm(float $maxTravelDistanceKm): self
    {
        $this->maxTravelDistanceKm = $maxTravelDistanceKm;
        return $this;
    }

    public function maxBacklogSize(int $maxBacklogSize): self
    {
        $this->maxBacklogSize = $maxBacklogSize;
        return $this;
    }

    /**
     * Serializes only the set fields (omits nulls).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::compact([
            'id' => $this->id,
            'tags' => $this->tags,
            'routingWeights' => $this->routingWeights,
            'skills' => Json::each($this->skills),
            'ip' => $this->ip,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'maxTravelDistanceKm' => $this->maxTravelDistanceKm,
            'maxBacklogSize' => $this->maxBacklogSize,
        ]);
    }
}
