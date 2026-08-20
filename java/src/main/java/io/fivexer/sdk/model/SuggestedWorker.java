package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * One candidate from a dry-run scoring pass.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SuggestedWorker {
    private String workerId;
    private boolean eligible;
    private double score;
    private double effectivePriority;
    private List<Map<String, Object>> reasons;  // free-form scoring explanations

    public String getWorkerId() { return workerId; }
    public boolean isEligible() { return eligible; }
    public double getScore() { return score; }
    public double getEffectivePriority() { return effectivePriority; }
    public List<Map<String, Object>> getReasons() { return reasons; }
}
