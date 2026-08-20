<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for consuming an emailed invite token and setting the PIN. The token is single-use.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class AcceptWorkerInvite
{
    public function __construct(
        /** Single-use, from the `?token=` of the emailed link. */
        private readonly string $token,
        /** 4-256 chars. */
        private readonly string $pin,
    ) {
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'pin' => $this->pin,
        ];
    }
}
