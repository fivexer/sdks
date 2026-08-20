<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Where workers log in, and whether the portal is switched on at all. A workspace with no published bundle is a normal state (`exists: false`), not an error.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerPortalLink
{
    public function __construct(
        public readonly bool $portalEnabled,
        public readonly string $portalUrl,
        /** False when no bundle has been published yet. */
        public readonly bool $exists,
        public readonly ?int $version,
        public readonly ?string $template,
        public readonly ?string $publishedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            portalEnabled: (bool) ($data['portalEnabled'] ?? false),
            portalUrl: (string) ($data['portalUrl'] ?? ''),
            exists: (bool) ($data['exists'] ?? false),
            version: isset($data['version']) ? (int) $data['version'] : null,
            template: isset($data['template']) ? (string) $data['template'] : null,
            publishedAt: isset($data['publishedAt']) ? (string) $data['publishedAt'] : null,
        );
    }
}
