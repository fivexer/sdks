package io.fivexer.sdk;

import io.fivexer.sdk.model.QuotaInfo;

/**
 * Raised when the /v1 API returns a non-2xx response.
 *
 * <p>Mirrors the platform error envelope {@code { "error": { "code", "message" } }}.
 * The {@link #getCode()} matches the API's error codes (e.g. {@code rate_limited},
 * {@code plan_limit_exceeded}, {@code not_found}, {@code task_exists},
 * {@code validation_failed}). On {@code 402}/{@code 429} the quota snapshot at the moment
 * of rejection is attached, plus {@link #getRetryAfterSeconds()} when present.
 */
public class FivexerApiException extends FivexerException {
    private static final long serialVersionUID = 1L;

    private final int statusCode;
    private final String code;
    private final QuotaInfo quota;
    private final Double retryAfterSeconds;

    public FivexerApiException(int statusCode, String code, String message, QuotaInfo quota, Double retryAfterSeconds) {
        super(message);
        this.statusCode = statusCode;
        this.code = code;
        this.quota = quota;
        this.retryAfterSeconds = retryAfterSeconds;
    }

    public int getStatusCode() {
        return statusCode;
    }

    public String getCode() {
        return code;
    }

    /** Quota state at the moment of rejection (present on 402/429), or {@code null}. */
    public QuotaInfo getQuota() {
        return quota;
    }

    /** Value of {@code Retry-After} (seconds) when present, or {@code null}. */
    public Double getRetryAfterSeconds() {
        return retryAfterSeconds;
    }

    @Override
    public String toString() {
        return "FivexerApiException{statusCode=" + statusCode + ", code='" + code + "', message='" + getMessage() + "'}";
    }
}
