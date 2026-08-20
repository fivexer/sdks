<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Where a supervisor can sign in from. Unauthenticated, and deliberately takes no workspace id — echoing one back would turn it into an existence oracle.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorEntry
{
    public function __construct(
        public readonly ?string $consoleUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            consoleUrl: isset($data['consoleUrl']) ? (string) $data['consoleUrl'] : null,
        );
    }
}
