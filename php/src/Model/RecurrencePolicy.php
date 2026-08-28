<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * How a recurring task repeats.
 *
 * A task created with a recurrence becomes a standing *template*, never itself matchable: the
 * platform materializes each occurrence as an ordinary scheduled task one interval ahead of its
 * window. Occurrences align to `startAt + k × everyMs` and never drift, so a template that was
 * down for an hour resumes on the original grid rather than an hour late.
 */
final class RecurrencePolicy
{
    /** @param int $everyMs Milliseconds between one occurrence's window opening and the next (min 60s) */
    public function __construct(
        public readonly int $everyMs,
        /** Epoch ms the first window opens. Default: now */
        private ?int $startAt = null,
        /** Offer window per occurrence; must be shorter than $everyMs */
        private ?int $windowMs = null,
        /** What an unserved window does to that occurrence: 'park' | 'drop' */
        private ?string $onMiss = null,
        /** No occurrence opens after this epoch ms; the template retires */
        private ?int $until = null,
        private ?int $maxOccurrences = null,
        /** 'skip' (default) resumes without back-filling elapsed slots; 'all' materializes them */
        private ?string $catchUp = null,
    ) {
    }

    public function startAt(int $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function windowMs(int $windowMs): self
    {
        $this->windowMs = $windowMs;
        return $this;
    }

    public function onMiss(string $onMiss): self
    {
        $this->onMiss = $onMiss;
        return $this;
    }

    public function until(int $until): self
    {
        $this->until = $until;
        return $this;
    }

    public function maxOccurrences(int $maxOccurrences): self
    {
        $this->maxOccurrences = $maxOccurrences;
        return $this;
    }

    public function catchUp(string $catchUp): self
    {
        $this->catchUp = $catchUp;
        return $this;
    }

    public function getStartAt(): ?int
    {
        return $this->startAt;
    }

    public function getWindowMs(): ?int
    {
        return $this->windowMs;
    }

    public function getOnMiss(): ?string
    {
        return $this->onMiss;
    }

    public function getUntil(): ?int
    {
        return $this->until;
    }

    public function getMaxOccurrences(): ?int
    {
        return $this->maxOccurrences;
    }

    public function getCatchUp(): ?string
    {
        return $this->catchUp;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = Json::compact([
            'startAt' => $this->startAt,
            'windowMs' => $this->windowMs,
            'onMiss' => $this->onMiss,
            'until' => $this->until,
            'maxOccurrences' => $this->maxOccurrences,
            'catchUp' => $this->catchUp,
        ]);
        $payload['everyMs'] = $this->everyMs;
        return $payload;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['everyMs'] ?? 0),
            isset($data['startAt']) ? (int) $data['startAt'] : null,
            isset($data['windowMs']) ? (int) $data['windowMs'] : null,
            isset($data['onMiss']) ? (string) $data['onMiss'] : null,
            isset($data['until']) ? (int) $data['until'] : null,
            isset($data['maxOccurrences']) ? (int) $data['maxOccurrences'] : null,
            isset($data['catchUp']) ? (string) $data['catchUp'] : null,
        );
    }
}
