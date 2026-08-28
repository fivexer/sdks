package io.fivexer.sdk.model;

/**
 * A {@code wt_} bearer token scoped to exactly one worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerSessionToken {
    private String token;
    private String expiresAt;

    public String getToken() { return token; }

    /**
     * ISO-8601 moment this token stops working, or null on a server predating the refresh
     * endpoint. Null rather than a defaulted timestamp: a made-up expiry would rotate either far
     * too early or never, and rotating only on a 401 puts a failed request in front of a person.
     */
    public String getExpiresAt() { return expiresAt; }
}
