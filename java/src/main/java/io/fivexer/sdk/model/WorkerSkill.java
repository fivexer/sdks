package io.fivexer.sdk.model;

/**
 * A skill as held by a worker, with the effective routing weight it contributes.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerSkill {
    private String skillId;
    private String key;
    private String name;
    private int level;
    private Double weightOverride;
    private double weight;

    public String getSkillId() { return skillId; }
    public String getKey() { return key; }
    public String getName() { return name; }
    public int getLevel() { return level; }
    public Double getWeightOverride() { return weightOverride; }
    public double getWeight() { return weight; }
}
