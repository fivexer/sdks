package io.fivexer.sdk.model;

import java.util.List;

/**
 * The result of a dry-run scoring pass.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SuggestWorkersResult {
    private List<String> tags;
    private double priority;
    private List<SuggestedWorker> workers;

    public List<String> getTags() { return tags; }
    public double getPriority() { return priority; }
    public List<SuggestedWorker> getWorkers() { return workers; }
}
