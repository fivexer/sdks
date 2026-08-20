package io.fivexer.sdk.model;

import java.util.Map;

/**
 * Per-tag learning statistics for one worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLearningSkillStat {
    private String tag;
    private int count;
    private double meanReward;
    private Double currentWeight;
    private Double learnedWeight;
    private Map<String, Object> skill;  // set when the tag maps to a registered skill

    public String getTag() { return tag; }
    public int getCount() { return count; }
    public double getMeanReward() { return meanReward; }
    public Double getCurrentWeight() { return currentWeight; }
    public Double getLearnedWeight() { return learnedWeight; }
    public Map<String, Object> getSkill() { return skill; }
}
