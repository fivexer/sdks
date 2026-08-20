<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * Worker portal credentials.
 *
 * Fluent builder: set what you need, then hand it to the client. {@see self::toArray()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
final class WorkerLogin
{
    public function __construct(
        private readonly string $workspaceId,
        private readonly string $workerId,
        private readonly string $pin,
    ) {
    }


    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }

    /** The worker this login authenticates as; adopted by the client on success. */
    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getPin(): string
    {
        return $this->pin;
    }

    /**
     * Serialise for the wire, omitting unset fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'workspaceId' => $this->workspaceId,
            'workerId' => $this->workerId,
            'pin' => $this->pin,
        ] + Json::compact([

        ]);
    }
}
