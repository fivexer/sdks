<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Input for redeeming a supervisor link.
 *
 * The token is single-use. Expired, already-used, revoked and never-existed all answer with the
 * same 400 `invalid_token` — the server refuses to tell a grinder which half of a guess was
 * right, so no caller can distinguish them either.
 */
final class AcceptSupervisorInvite
{
    public function __construct(
        /** Single-use, from the `?token=` of the supervisor link. */
        private readonly string $token,
    ) {
    }

    /**
     * Serialise for the wire.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['token' => $this->token];
    }
}
