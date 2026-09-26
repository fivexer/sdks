<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * The corrected (or newly recorded) entry beside the correction that produced it.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TimeEntryCorrectionResult
{
    public function __construct(
        public readonly CorrectedTimeEntry $entry,
        /** The shift's own trail entry */
        public readonly TimeCorrection $correction,
        /**
         * Present when the shift was saved with its breaks: every break it holds afterwards.
         * Null when the change did not touch the breaks.
         *
         * @var list<CorrectedTimeEntry>|null
         */
        public readonly ?array $breaks = null,
        /**
         * With breaks: one trail entry per record touched, the shift's first.
         *
         * @var list<TimeCorrection>|null
         */
        public readonly ?array $corrections = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        /** @var list<CorrectedTimeEntry>|null $breaks */
        $breaks = Json::parseEachOrNull($data, 'breaks', [CorrectedTimeEntry::class, 'fromArray']);
        /** @var list<TimeCorrection>|null $corrections */
        $corrections = Json::parseEachOrNull($data, 'corrections', [TimeCorrection::class, 'fromArray']);
        return new self(
            entry: CorrectedTimeEntry::fromArray($data['entry'] ?? []),
            correction: TimeCorrection::fromArray($data['correction'] ?? []),
            breaks: $breaks,
            corrections: $corrections,
        );
    }
}
