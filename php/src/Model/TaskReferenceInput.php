<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A reference being attached to a task. The id is assigned by the API, so this input shape
 * deliberately has none — {@see TaskReference} is the read-back model that carries one.
 */
final class TaskReferenceInput
{
    public function __construct(
        private readonly string $url,
        private ?string $label = null,
        private ?string $contentType = null,
    ) {
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function contentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
        ] + Json::compact([
            'label' => $this->label,
            'contentType' => $this->contentType,
        ]);
    }
}
