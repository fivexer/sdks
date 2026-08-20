<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for self-registering through a QR join link. The worker id is generated server-side.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class JoinWorkspace
{
    public function __construct(
        private readonly string $token,
        private readonly string $name,
        /** 4-256 chars. */
        private readonly string $pin,
        private ?string $email = null,
    ) {
    }

    public function email(string $email): self
    {
        $this->email = $email;
        return $this;
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
            'name' => $this->name,
            'pin' => $this->pin,
        ] + Json::compact([
            'email' => $this->email,
        ]);
    }
}
