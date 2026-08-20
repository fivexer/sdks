<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for setting a worker's PIN directly, for someone who will never receive email (a kiosk, an agent).
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class CreateWorkerIdentity
{
    public function __construct(
        private readonly string $label,
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
            'label' => $this->label,
            'pin' => $this->pin,
        ] + Json::compact([
            'email' => $this->email,
        ]);
    }
}
