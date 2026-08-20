package io.fivexer.sdk.model;

import java.util.List;

/**
 * Outcome of a bulk create. A 200 does not mean everything was created: read {@code failed} and the per-entry results, whose {@code index} maps back to the input list.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class BulkTaskReport {
    private Integer created;
    private Integer failed;
    private List<BulkTaskResult> results;

    public Integer getCreated() { return created; }
    public Integer getFailed() { return failed; }
    public List<BulkTaskResult> getResults() { return results; }
}
