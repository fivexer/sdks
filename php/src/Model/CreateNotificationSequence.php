<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for creating a notification sequence.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class CreateNotificationSequence
{
    public function __construct(
        private readonly string $name,
        /** @var list<NotificationSequenceStep> */
        private readonly array $steps,
        private ?bool $enabled = null,
        /** @var list<string> */
        private ?array $filterTags = null,
    ) {
    }

    public function enabled(bool $enabled): self
    {
        $this->enabled = $enabled;
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
        return [
            'name' => $this->name,
            'steps' => Json::each($this->steps),
        ] + Json::compact([
            'enabled' => $this->enabled,
            'filterTags' => $this->filterTags,
        ]);
    }
}
