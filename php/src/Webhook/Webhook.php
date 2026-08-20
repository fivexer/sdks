<?php

declare(strict_types=1);

namespace Fivexer\SDK\Webhook;

use Fivexer\SDK\Exception\FivexerException;

/**
 * Webhook signature verification for the x-fivexer-signature header.
 *
 * Mirrors the platform's signing exactly:
 *   header  : x-fivexer-signature
 *   format  : t=<unix-ms>,v1=<hex64 HMAC-SHA256>
 *   message : "<timestamp>.<raw-body>"
 *   replay  : rejected if |now - timestamp| > tolerance (default +/- 5 min)
 *   compare : hash_equals (constant-time)
 *
 * The body must be the RAW request body bytes - exactly what was received, before
 * any JSON re-serialization, otherwise the HMAC will not match.
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'x-fivexer-signature';
    public const DEFAULT_TOLERANCE_MS = 5 * 60 * 1000;

    /**
     * Verify the signature and return the parsed event.
     *
     * @param string $payload Raw request body
     * @param string $header The signature header value
     * @param string $secret The webhook secret
     * @param int<0, max>|null $toleranceMs Replay-window tolerance in ms; null = default
     * @param int<0, max>|null $nowMs Current time in ms; null = system clock (for testing)
     * @throws FivexerException on any verification or parse failure
     */
    public static function constructEvent(
        string $payload,
        string $header,
        string $secret,
        ?int $toleranceMs = null,
        ?int $nowMs = null,
    ): WebhookEvent {
        if (!self::verifySignature($payload, $header, $secret, $toleranceMs, $nowMs)) {
            throw new FivexerException('webhook signature verification failed');
        }
        return WebhookEvent::fromPayload($payload);
    }

    /**
     * @return bool true iff the header is well-formed, within tolerance, and the HMAC matches.
     */
    public static function verifySignature(
        string $payload,
        string $header,
        string $secret,
        ?int $toleranceMs = null,
        ?int $nowMs = null,
    ): bool {
        $parsed = self::parseSignatureHeader($header);
        if ($parsed === null) {
            return false;
        }
        [$timestamp, $signatureHex] = $parsed;

        $tolerance = $toleranceMs ?? self::DEFAULT_TOLERANCE_MS;
        $now = $nowMs ?? (int) (\microtime(true) * 1000);
        if (\abs($now - $timestamp) > $tolerance) {
            return false;
        }

        $expected = self::computeSignature($secret, $timestamp, $payload);
        return \hash_equals($expected, $signatureHex);
    }

    /** @return array{0:int<0,max>,1:string}|null [timestampMs, signatureHex64lowercase] or null if malformed. */
    private static function parseSignatureHeader(string $header): ?array
    {
        $header = \trim($header);
        $parts = \explode(',', $header);
        if (\count($parts) !== 2) {
            return null;
        }
        $tPart = \trim($parts[0]);
        $vPart = \trim($parts[1]);
        if (!\str_starts_with($tPart, 't=') || !\str_starts_with($vPart, 'v1=')) {
            return null;
        }
        $tsStr = \substr($tPart, 2);
        $sig = \strtolower(\substr($vPart, 3));
        if ($tsStr === '' || !\ctype_digit($tsStr)) {
            return null;
        }
        // exactly 64 lowercase hex chars (sha256)
        if (\strlen($sig) !== 64 || !\ctype_xdigit($sig)) {
            return null;
        }
        // ctype_digit guarantees a non-negative integer.
        return [(int) $tsStr, $sig];
    }

    private static function computeSignature(string $secret, int $timestamp, string $body): string
    {
        $message = $timestamp . '.' . $body;
        return \hash_hmac('sha256', $message, $secret);
    }
}
