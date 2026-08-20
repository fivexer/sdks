package io.fivexer.sdk.model;

/**
 * One entry of a bulk create. The wire sends a union — either an id or an error — so exactly one of {@code id}/{@code error} is set; {@link #isOk()} is the discriminator.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class BulkTaskResult {
    private Integer index;
    private String id;
    private String status;
    private BulkTaskError error;

    public Integer getIndex() { return index; }
    public String getId() { return id; }
    public String getStatus() { return status; }
    public BulkTaskError getError() { return error; }

    /** True when this entry was created; false when it carries an error instead. */
    public boolean isOk() { return error == null; }
}
