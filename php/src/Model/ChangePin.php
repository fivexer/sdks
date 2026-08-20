<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Input for a worker changing their own PIN from the portal.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class ChangePin
{
    public function __construct(
        private readonly string $currentPin,
        /** 4-256 chars. */
        private readonly string $newPin,
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
            'currentPin' => $this->currentPin,
            'newPin' => $this->newPin,
        ];
    }
}
