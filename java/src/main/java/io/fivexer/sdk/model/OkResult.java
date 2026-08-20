package io.fivexer.sdk.model;

/**
 * Envelope for the endpoints that answer {@code {"ok": true}}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class OkResult {
    private boolean ok;

    public boolean isOk() { return ok; }
}
