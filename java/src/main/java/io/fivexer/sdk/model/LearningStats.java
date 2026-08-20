package io.fivexer.sdk.model;

/**
 * Aggregate reward statistics.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class LearningStats {
    private int decisions;
    private int rewards;
    private double totalReward;
    private double averageReward;

    public int getDecisions() { return decisions; }
    public int getRewards() { return rewards; }
    public double getTotalReward() { return totalReward; }
    public double getAverageReward() { return averageReward; }
}
