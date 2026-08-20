package io.fivexer.sdk.model;

import java.util.Map;

/**
 * Learning configuration plus its current statistics.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class LearningStatus {
    private boolean enabled;
    private boolean shadowMode;  // true = the model scores but never influences routing
    private boolean autoWeights;
    private Map<String, Double> signalWeights;
    private Map<String, Double> rewards;
    private LearningStats stats;  // null until the first reward lands
    private int modelSize;

    public boolean isEnabled() { return enabled; }
    public boolean isShadowMode() { return shadowMode; }
    public boolean isAutoWeights() { return autoWeights; }
    public Map<String, Double> getSignalWeights() { return signalWeights; }
    public Map<String, Double> getRewards() { return rewards; }
    public LearningStats getStats() { return stats; }
    public int getModelSize() { return modelSize; }
}
