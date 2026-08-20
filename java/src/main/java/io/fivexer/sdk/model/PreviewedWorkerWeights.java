package io.fivexer.sdk.model;

import java.util.Map;

/**
 * Current vs learned routing weights for one worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class PreviewedWorkerWeights {
    private String workerId;
    private Map<String, Double> current;
    private Map<String, Double> learned;

    public String getWorkerId() { return workerId; }
    public Map<String, Double> getCurrent() { return current; }
    public Map<String, Double> getLearned() { return learned; }
}
