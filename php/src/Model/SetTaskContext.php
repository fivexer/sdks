<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for `tasks()->context()->set()` - a full replace of a task's rich data.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class SetTaskContext
{
    public function __construct(
        private ?string $title = null,
        private ?string $description = null,
        /** @var array<string, mixed> */
        private ?array $context = null,
        /** @var list<TaskReference> */
        private ?array $references = null,
    ) {
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

    /** @param list<TaskReference> $references */
    public function references(array $references): self
    {
        $this->references = $references;
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
            'title' => $this->title,
            'description' => $this->description,
            'context' => $this->context,
            'references' => Json::each($this->references),
        ]);
        return $payload;
    }
}
