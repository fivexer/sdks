<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Partial in-place worker update - absent fields keep their stored value.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class PatchWorker
{
    public function __construct(
        /** @var list<string> */
        private ?array $tags = null,
        /** @var array<string, float> */
        private ?array $routingWeights = null,
        /** @var list<WorkerSkillAssignment> */
        private ?array $skills = null,
        private ?string $ip = null,
        private ?float $latitude = null,
        private ?float $longitude = null,
        private ?float $maxTravelDistanceKm = null,
        /** per-worker backlog cap; 0 = receive nothing */
        private ?int $maxBacklogSize = null,
        private ?string $locale = null,
    ) {
    }

    /** Distinguishes "leave the language alone" from "erase it" — both look like null. */
    private bool $clearingLocale = false;

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

    /** The worker's language — what their push notifications and emails are composed in. */
    public function locale(string $locale): self
    {
        $this->locale = $locale;
        $this->clearingLocale = false;
        return $this;
    }

    /** Erase the stored language, returning them to the workspace default. Sends null. */
    public function clearLocale(): self
    {
        $this->locale = null;
        $this->clearingLocale = true;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Json::compact([
            'tags' => $this->tags,
            'routingWeights' => $this->routingWeights,
            'skills' => Json::each($this->skills),
            'ip' => $this->ip,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'maxTravelDistanceKm' => $this->maxTravelDistanceKm,
            'maxBacklogSize' => $this->maxBacklogSize,
            'locale' => $this->locale,
        ]);
        if ($this->clearingLocale) {
            $payload['locale'] = null;
        }
        return $payload;
    }
}
