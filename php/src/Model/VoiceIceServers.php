<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * **Experimental** — see {@see VoiceIceServer}.
 *
 * The answer to `voiceIce()` on either session plane.
 */
final class VoiceIceServers
{
    /** @param list<VoiceIceServer> $iceServers */
    public function __construct(public readonly array $iceServers = [])
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<VoiceIceServer> $servers */
        $servers = Json::parseEach($data, 'iceServers', [VoiceIceServer::class, 'fromArray']);
        return new self($servers);
    }
}
