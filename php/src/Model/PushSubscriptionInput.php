<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A browser Web Push subscription, shaped as `PushSubscription.toJSON()` returns it.
 *
 * The wire nests the two keys under a `keys` object while the browser hands them over flat, so
 * this builds its body explicitly rather than mapping property names.
 */
final class PushSubscriptionInput
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $p256dh,
        private readonly string $auth,
    ) {
    }

    /**
     * Serialise for the wire, nesting the keys the way the API expects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ],
        ];
    }
}
