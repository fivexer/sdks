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
    ) {
        if ($tags === []) {
            throw new \InvalidArgumentException('tags must not be empty');
        }
    }

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

    /**
     * Serializes only the set fields (omits nulls) so the request body matches the contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
        ]);
    }
}
