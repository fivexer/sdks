<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** A stretch of a shift worked in one role, as a time-entry write states it. */
final class RoleStretchInput
{
    public function __construct(
        /** A role id on the workspace rate card; null is time in no particular role. */
        private readonly ?string $positionId,
        /** ISO-8601 */
        private readonly string $startedAt,
        /** ISO-8601; null only for the stretch still being worked on a running shift. */
        private readonly ?string $endedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'positionId' => $this->positionId,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
        ];
    }
}
