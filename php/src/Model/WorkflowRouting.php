<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A branch rule. `condition` is evaluated against the step result and the first match wins.
 * Used both when saving a definition and when reading one back.
 */
final class WorkflowRouting
{
    public function __construct(
        public readonly string $condition,
        public readonly string $targetStepId,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['condition' => $this->condition, 'targetStepId' => $this->targetStepId];
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            condition: (string) ($data['condition'] ?? ''),
            targetStepId: (string) ($data['targetStepId'] ?? ''),
        );
    }
}
