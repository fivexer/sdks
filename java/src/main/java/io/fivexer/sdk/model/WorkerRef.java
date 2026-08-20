package io.fivexer.sdk.model;

/**
 * Envelope for endpoints that answer with just a worker id.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerRef {
    private String id;

    public String getId() { return id; }
}
