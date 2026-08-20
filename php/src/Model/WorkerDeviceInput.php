<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * An Expo push token from the native app. Browsers use {@see PushSubscriptionInput} instead.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class WorkerDeviceInput
{
    public function __construct(
        private readonly string $token,
        /** 'ios' | 'android' | 'unknown' */
        private ?string $platform = null,
    ) {
    }

    public function platform(string $platform): self
    {
        $this->platform = $platform;
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
        ] + Json::compact([
            'platform' => $this->platform ?? 'unknown',
        ]);
    }
}
