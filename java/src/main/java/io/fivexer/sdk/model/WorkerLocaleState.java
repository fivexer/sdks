package io.fivexer.sdk.model;

/**
 * A worker's own language, after setting it.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLocaleState {
    private String workerId;
    private String locale;

    public String getWorkerId() { return workerId; }
    public String getLocale() { return locale; }
}
