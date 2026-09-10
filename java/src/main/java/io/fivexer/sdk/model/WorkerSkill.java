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
    private String validFrom;
    private String validUntil;

    public String getSkillId() { return skillId; }
    public String getKey() { return key; }
    public String getName() { return name; }
    public int getLevel() { return level; }
    public Double getWeightOverride() { return weightOverride; }
    public double getWeight() { return weight; }

    /** Inclusive ISO day it became valid; null when unbounded. */
    public String getValidFrom() { return validFrom; }

    /** Inclusive <em>last</em> day it may be relied on; null when it does not expire. */
    public String getValidUntil() { return validUntil; }
}
