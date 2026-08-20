<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Result of inviting a worker. `inviteUrl` is credential-equivalent until consumed; it is returned anyway because `emailStatus` is often `mailer_unconfigured`, which leaves sharing the link as the only delivery path.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerInviteResult
{
    public function __construct(
        public readonly PublicWorkerIdentity $identity,
        public readonly bool $workerCreated,
        /** 'sent' | 'mailer_unconfigured' | 'send_failed' */
        public readonly string $emailStatus,
        public readonly string $inviteUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            identity: PublicWorkerIdentity::fromArray($data['identity'] ?? []),
            workerCreated: (bool) ($data['workerCreated'] ?? false),
            emailStatus: (string) ($data['emailStatus'] ?? ''),
            inviteUrl: (string) ($data['inviteUrl'] ?? ''),
        );
    }
}
