<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One step of a notification sequence: fire `eventType` at `offsetMs` after `trigger`.
 *
 * Serves as both an input model (build one, hand it to a sequence) and a response model
 * ({@see self::fromArray()}), since the shape is identical in both directions.
 */
final class NotificationSequenceStep
{
    public function __construct(
        private readonly string $trigger,
        private readonly string $eventType,
        private ?int $offsetMs = null,
    ) {
    }

    public function offsetMs(int $offsetMs): self
    {
        $this->offsetMs = $offsetMs;
        return $this;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getOffsetMs(): ?int
    {
        return $this->offsetMs;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trigger' => $this->trigger,
            'eventType' => $this->eventType,
        ] + Json::compact(['offsetMs' => $this->offsetMs]);
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trigger: (string) ($data['trigger'] ?? ''),
            eventType: (string) ($data['eventType'] ?? ''),
            offsetMs: isset($data['offsetMs']) ? (int) $data['offsetMs'] : null,
        );
    }
}
