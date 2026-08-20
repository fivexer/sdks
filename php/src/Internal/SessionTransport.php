<?php

declare(strict_types=1);

namespace Fivexer\SDK\Internal;

use Fivexer\SDK\Exception\FivexerApiException;

/**
 * Retry and error-mapping shared by the two session planes.
 *
 * The worker (`wt_`) and supervisor (`sv_`) clients answer with the same
 * `{"error": {code, message}}` envelope and neither carries quota headers, so they need the
 * same three decisions: is this status worth retrying, how long does `retry-after` ask us to
 * wait, and what exception does this body become. Kept here rather than copied into both, so
 * the envelope's shape has one definition instead of two that can drift apart.
 *
 * The workspace plane is deliberately not a user: it also parses `X-Quota-*` onto the client
 * and attaches that snapshot to its errors, which is a different mapping.
 *
 * @internal
 */
trait SessionTransport
{
    private static function isRetryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private static function retryAfterSeconds(string $value): ?float
    {
        if ($value === '' || !\is_numeric($value)) {
            return null;
        }
        return \max(0.0, (float) $value);
    }

    private static function toApiException(int $status, string $rawBody): FivexerApiException
    {
        $code = 'unknown_error';
        $message = 'http ' . $status;

        $decoded = \json_decode($rawBody, true);
        if (\is_array($decoded) && isset($decoded['error']) && \is_array($decoded['error'])) {
            $error = $decoded['error'];
            if (isset($error['code']) && \is_string($error['code'])) {
                $code = $error['code'];
            }
            if (isset($error['message']) && \is_string($error['message'])) {
                $message = $error['message'];
            }
        }

        return new FivexerApiException($status, $code, $message);
    }
}
