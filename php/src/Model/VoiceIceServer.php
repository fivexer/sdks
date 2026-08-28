<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * **Experimental — voice is not production-ready.** This surface may change or be withdrawn in a
 * patch release; do not build on it yet.
 *
 * One STUN/TURN server, shaped for a WebRTC `RTCIceServer`. Unlike the console's read view this
 * *does* carry `credential`: a worker or supervisor about to place a call needs the TURN secret
 * to authenticate to the relay, so the two reads differ deliberately.
 *
 * `urls` arrives as a string or a list of strings; both are normalised to a list here so a
 * caller iterating does not have to branch on which form the server sent.
 */
final class VoiceIceServer
{
    /** @param list<string> $urls */
    public function __construct(
        public readonly array $urls,
        public readonly ?string $username = null,
        /** The TURN shared secret. Present on the session planes, withheld from the console read */
        public readonly ?string $credential = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $raw = $data['urls'] ?? null;
        if (\is_array($raw)) {
            $urls = \array_values(\array_map(static fn (mixed $u): string => (string) $u, $raw));
        } elseif (\is_string($raw)) {
            $urls = [$raw];
        } else {
            // Voice is experimental and the server's shape may still move; a malformed relay
            // entry must not take down call setup for the well-formed ones beside it.
            $urls = [];
        }
        return new self(
            $urls,
            isset($data['username']) ? (string) $data['username'] : null,
            isset($data['credential']) ? (string) $data['credential'] : null,
        );
    }
}
