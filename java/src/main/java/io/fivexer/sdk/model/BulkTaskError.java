package io.fivexer.sdk.model;

/**
 * Why one entry of a bulk create failed.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class BulkTaskError {
    private String code;
    private String message;

    public String getCode() { return code; }
    public String getMessage() { return message; }
}
