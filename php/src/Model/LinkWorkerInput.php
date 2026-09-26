<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for `workers()->link()` — who this worker is in the connected system.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class LinkWorkerInput
{
    public function __construct(
        /** The person's id in the connected system (HubSpot owner id, Jira account id, …) */
        private readonly string $vendorUserId,
        /** @var list<string>|null */
        private ?array $tags = null,
    ) {
    }

    /**
     * Added to the worker's own tags — a union, never a replacement.
     *
     * @param list<string> $tags
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['vendorUserId' => $this->vendorUserId] + Json::compact(['tags' => $this->tags]);
    }
}
