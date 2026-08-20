package io.fivexer.sdk.model;

/**
 * A {@code wt_} bearer token scoped to exactly one worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerSessionToken {
    private String token;

    public String getToken() { return token; }
}
