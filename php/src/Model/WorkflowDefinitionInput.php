<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The body accepted by `workflows()->save()`. The workflow id comes from the URL and is
 * deliberately never sent in the body.
 */
final class WorkflowDefinitionInput
{
    /**
     * @param list<WorkflowStep> $steps
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        private readonly string $name,
        private readonly array $steps,
        private ?int $version = null,
        private ?string $initialStepId = null,
        private ?int $defaultTimeoutMs = null,
        private ?array $metadata = null,
    ) {
    }

    public function version(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function initialStepId(string $initialStepId): self
    {
        $this->initialStepId = $initialStepId;
        return $this;
    }

    public function defaultTimeoutMs(int $defaultTimeoutMs): self
    {
        $this->defaultTimeoutMs = $defaultTimeoutMs;
        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'steps' => Json::each($this->steps),
        ] + Json::compact([
            'version' => $this->version,
            'initialStepId' => $this->initialStepId,
            'defaultTimeoutMs' => $this->defaultTimeoutMs,
            'metadata' => $this->metadata,
        ]);
    }
}
