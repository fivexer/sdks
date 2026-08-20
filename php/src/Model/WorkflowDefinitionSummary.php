<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A workflow definition as it appears in a listing.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowDefinitionSummary
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
        );
    }
}
