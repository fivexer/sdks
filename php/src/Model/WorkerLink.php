<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * "This worker is that person in a connected system."
 *
 * Connectors name the people they import `<connector>-<vendorUserId>` and write back by decoding
 * that id. A worker who was here before the system was connected has some other id; a link lets
 * them stand for their vendor user (HubSpot owner, Jira account, …) instead of being imported a
 * second time.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerLink
{
    public function __construct(
        /** Connector id: 'hubspot', 'salesforce', 'pipedrive', 'jira', 'scoro', … */
        public readonly string $connector,
        public readonly string $vendorUserId,
        public readonly string $workerId,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            connector: (string) ($data['connector'] ?? ''),
            vendorUserId: (string) ($data['vendorUserId'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            createdAt: (string) ($data['createdAt'] ?? ''),
        );
    }
}
