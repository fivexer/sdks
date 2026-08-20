package io.fivexer.sdk.model;

/**
 * Where a supervisor can sign in from. Unauthenticated, and deliberately takes no workspace id — echoing one back would turn it into an existence oracle.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorEntry {
    private String consoleUrl;

    public String getConsoleUrl() { return consoleUrl; }
}
