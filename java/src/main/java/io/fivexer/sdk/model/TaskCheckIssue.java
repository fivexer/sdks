package io.fivexer.sdk.model;

/**
 * One problem found by the dry-run check.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskCheckIssue {
    private String severity;
    private String code;
    private String message;
    private String tag;

    public String getSeverity() { return severity; }
    public String getCode() { return code; }
    public String getMessage() { return message; }
    public String getTag() { return tag; }
}
