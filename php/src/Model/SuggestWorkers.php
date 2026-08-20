<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Dry-run scoring: who *would* match these tags, without creating a task.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class SuggestWorkers
{
    public function __construct(
        /** @var list<string> */
        private readonly array $tags,
        private ?float $priority = null,
        /** @var list<RequiredSkill> */
        private ?array $requiredSkills = null,
        /** @var list<string> */
        private ?array $vetoedWorkers = null,
        private ?int $limit = null,
        private ?float $latitude = null,
        private ?float $longitude = null,
        private ?float $maxDistanceKm = null,
        private ?bool $requireGeo = null,
        /** @var list<string> */
        private ?array $allowedCidrs = null,
    ) {
    }

    public function priority(float $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /** @param list<RequiredSkill> $requiredSkills */
    public function requiredSkills(array $requiredSkills): self
    {
        $this->requiredSkills = $requiredSkills;
        return $this;
    }

    /** @param list<string> $vetoedWorkers */
    public function vetoedWorkers(array $vetoedWorkers): self
    {
        $this->vetoedWorkers = $vetoedWorkers;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
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

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tags' => $this->tags,
        ] + Json::compact([
            'priority' => $this->priority,
            'requiredSkills' => Json::each($this->requiredSkills),
            'vetoedWorkers' => $this->vetoedWorkers,
            'limit' => $this->limit,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'maxDistanceKm' => $this->maxDistanceKm,
            'requireGeo' => $this->requireGeo,
            'allowedCidrs' => $this->allowedCidrs,
        ]);
    }
}
