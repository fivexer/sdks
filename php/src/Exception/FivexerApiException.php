<?php

declare(strict_types=1);

namespace Fivexer\SDK\Exception;

use Fivexer\SDK\Model\QuotaInfo;

/**
 * Raised when the /v1 API returns a non-2xx response.
 *
 * Mirrors the platform error envelope { "error": { "code", "message" } }.
 * `$apiCode` carries the API's error code (e.g. rate_limited, plan_limit_exceeded,
 * not_found, task_exists, validation_failed) — not the inherited getCode(), which is
 * PHP's numeric exception code and is always 0 here. On 402/429 the quota snapshot at
 * the moment of rejection is attached, plus retry-after.
 */
class FivexerApiException extends FivexerException
{
    /**
     * @param int $statusCode HTTP status code returned by the API
     * @param string $apiCode API error code (e.g. 'rate_limited')
     * @param string $message Human-readable error message
     * @param QuotaInfo|null $quota Quota state at the moment of rejection (402/429)
     * @param float|null $retryAfterSeconds Value of Retry-After (seconds) when present
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $apiCode,
        string $message,
        public readonly ?QuotaInfo $quota = null,
        public readonly ?float $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function __toString(): string
    {
        return 'FivexerApiException{statusCode=' . $this->statusCode
            . ', apiCode=\'' . $this->apiCode . '\', message=\'' . $this->getMessage() . '\'}';
    }
}
