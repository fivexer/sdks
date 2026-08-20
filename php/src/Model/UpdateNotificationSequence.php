<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Partial sequence update - absent fields keep their stored value.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class UpdateNotificationSequence
{
    public function __construct(
        private ?string $name = null,
        private ?bool $enabled = null,
        /** @var list<NotificationSequenceStep> */
        private ?array $steps = null,
        /** @var list<string> */
        private ?array $filterTags = null,
    ) {
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function enabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /** @param list<NotificationSequenceStep> $steps */
    public function steps(array $steps): self
    {
        $this->steps = $steps;
        return $this;
    }

    /** @param list<string> $filterTags */
    public function filterTags(array $filterTags): self
    {
        $this->filterTags = $filterTags;
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
            'name' => $this->name,
            'enabled' => $this->enabled,
            'steps' => Json::each($this->steps),
            'filterTags' => $this->filterTags,
        ]);
        return $payload;
    }
}
